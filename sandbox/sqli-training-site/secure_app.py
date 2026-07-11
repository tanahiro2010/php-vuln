"""
secure_app.py -- Hifumi Secure Shop (SECURE VERSION)
=====================================================

This is the fixed half of the SQL injection training pair. Every route here
mirrors a route in app.py, but every query is built with a parameterized
statement ("?" placeholders) instead of string concatenation, and every
numeric input is validated before use.

Try the exact same attack inputs from README.md against this app (port 5010)
and compare: the login bypass fails, the UNION leaks return nothing, and
malformed ids are rejected with a clear error instead of changing the query.

Run:
    python init_db.py
    python secure_app.py
Then open http://127.0.0.1:5010
"""

import os
import sqlite3

from flask import Flask, request, session, redirect, url_for, render_template, flash

DB_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "database.db")

app = Flask(__name__)
app.secret_key = "training-demo-secure-secret-key"  # fine for a local teaching app only
# Distinct cookie name so this app doesn't clobber app.py's session when both
# are open on http://127.0.0.1 at the same time (different port, same host).
app.config["SESSION_COOKIE_NAME"] = "hifumi_secure_session"

SUSPICIOUS_KEYWORDS = ("union", "sleep", "--", "'", '"', " or ", "1=1", "1=2")


def looks_suspicious(raw: str) -> bool:
    """Heuristic only, used to show a teaching notice. It has no effect on
    query safety -- the placeholders below are what actually keep this app
    safe, regardless of what this function returns."""
    low = raw.lower()
    return any(k in low for k in SUSPICIOUS_KEYWORDS)


def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


@app.context_processor
def inject_mode():
    return {"MODE_LABEL": "SECURE VERSION", "MODE_CLASS": "secure"}


# ---------------------------------------------------------------------------
# Error handling: never show a blank/white error page during a live demo.
# ---------------------------------------------------------------------------
@app.errorhandler(400)
def handle_400(e):
    flash(f"リクエストエラー: {e}", "danger")
    return render_template("index.html"), 400


@app.errorhandler(404)
def handle_404(e):
    flash("ページが見つかりません。", "warn")
    return render_template("index.html"), 404


@app.errorhandler(500)
def handle_500(e):
    flash("サーバー内部エラーが発生しました。", "danger")
    return render_template("index.html"), 500


@app.route("/")
def index():
    return render_template("index.html")


# ---------------------------------------------------------------------------
# Scenario 1 & 2 (fixed): generic error message, parameterized login query
# ---------------------------------------------------------------------------
@app.route("/login", methods=["GET", "POST"])
def login():
    if request.method == "GET":
        return render_template("login.html")

    username = request.form.get("username", "")
    password = request.form.get("password", "")
    suspicious = looks_suspicious(username) or looks_suspicious(password)

    db = get_db()
    # FIX: parameterized query -- the "'--" / "' OR '1'='1" tricks that break
    # app.py's login are treated as plain, literal text here.
    user = db.execute(
        "SELECT * FROM users WHERE username = ? AND password = ?",
        (username, password),
    ).fetchone()

    if user:
        session["user"] = user["username"]
        session["role"] = user["role"]
        flash(f"Logged in as {user['username']}", "ok")
        return redirect(url_for("dashboard"))

    # FIX: identical, generic message whether the username exists or not --
    # no enumeration signal is leaked either way.
    login_error = "ユーザー名またはパスワードが正しくありません"
    return render_template(
        "login.html", login_error=login_error, last_username=username, suspicious=suspicious
    )


@app.route("/logout")
def logout():
    session.clear()
    flash("ログアウトしました。", "info")
    return redirect(url_for("index"))


# ---------------------------------------------------------------------------
# Scenario 7 (part 1): registration -- parameterized in both versions.
# ---------------------------------------------------------------------------
@app.route("/register", methods=["POST"])
def register():
    username = request.form.get("username", "").strip()
    password = request.form.get("password", "")
    if not username or not password:
        flash("ユーザー名とパスワードを入力してください。", "warn")
        return redirect(url_for("login"))

    db = get_db()
    db.execute(
        "INSERT INTO users (username, password, role, email) VALUES (?, ?, 'staff', ?)",
        (username, password, f"{username}@example.local"),
    )
    db.commit()
    flash(
        f"ユーザー '{username}' を登録しました。管理者レポート画面でも安全に処理されることを確認してください。",
        "info",
    )
    return redirect(url_for("login"))


@app.route("/dashboard")
def dashboard():
    db = get_db()
    total_customers = db.execute("SELECT COUNT(*) FROM customers").fetchone()[0]
    open_contacts = db.execute("SELECT COUNT(*) FROM contacts").fetchone()[0]
    monthly_orders = db.execute("SELECT COUNT(*) FROM orders").fetchone()[0]
    admin_row = db.execute("SELECT username FROM users WHERE role = 'admin'").fetchone()
    admin_user = admin_row["username"] if admin_row else "N/A"
    return render_template(
        "dashboard.html",
        total_customers=total_customers,
        open_contacts=open_contacts,
        monthly_orders=monthly_orders,
        admin_user=admin_user,
    )


# ---------------------------------------------------------------------------
# Scenario 3 (fixed): parameterized product search, no UNION leak possible
# ---------------------------------------------------------------------------
@app.route("/catalog")
def catalog():
    q = request.args.get("q", "")
    suspicious = looks_suspicious(q)
    db = get_db()
    # FIX: parameterized query -- "%' UNION SELECT ..." is treated as a
    # literal search string, so it simply matches no product names.
    rows = db.execute(
        "SELECT id, name, price FROM products WHERE name LIKE ?", (f"%{q}%",)
    ).fetchall()
    return render_template(
        "catalog.html", q=q, rows=rows, flagged=False, error=None, suspicious=suspicious
    )


# ---------------------------------------------------------------------------
# Scenario 4 (fixed): validated integer id + parameterized query
# ---------------------------------------------------------------------------
@app.route("/customer")
def customer_detail():
    raw_id = request.args.get("id", "1")
    suspicious = looks_suspicious(raw_id) or not raw_id.strip().lstrip("-").isdigit()

    if not raw_id.strip().lstrip("-").isdigit():
        # FIX: reject anything that isn't a plain integer before it ever
        # reaches SQL, instead of concatenating it into a numeric context.
        return render_template(
            "customer_detail.html",
            raw_id=raw_id,
            customer=None,
            error="idは整数で指定してください。",
            suspicious=suspicious,
        ), 400

    db = get_db()
    customer = db.execute(
        "SELECT id, name, email, phone, note FROM customers WHERE id = ?", (int(raw_id),)
    ).fetchone()
    return render_template(
        "customer_detail.html", raw_id=raw_id, customer=customer, error=None, suspicious=suspicious
    )


# ---------------------------------------------------------------------------
# Scenario 5 (fixed): validated integer order_id, no delay logic at all
# ---------------------------------------------------------------------------
@app.route("/track")
def track_order():
    raw_order_id = request.args.get("order_id", "1")
    suspicious = looks_suspicious(raw_order_id) or not raw_order_id.strip().isdigit()

    if not raw_order_id.strip().isdigit():
        # FIX: reject non-numeric input outright. There is no pseudo-SLEEP
        # branch here at all -- a fixed app has nothing for that kind of
        # payload to trigger.
        return render_template(
            "order_track.html",
            raw_order_id=raw_order_id,
            order=None,
            error="order_idは整数で指定してください。",
            delayed=False,
            suspicious=suspicious,
        ), 400

    db = get_db()
    order = db.execute(
        "SELECT id, tracking_code, status FROM orders WHERE id = ?", (int(raw_order_id),)
    ).fetchone()
    return render_template(
        "order_track.html",
        raw_order_id=raw_order_id,
        order=order,
        error=None,
        delayed=False,
        suspicious=suspicious,
    )


# ---------------------------------------------------------------------------
# Scenario 6 (fixed): parameterized contact search, internal_memo stays hidden
# ---------------------------------------------------------------------------
@app.route("/contacts")
def contacts():
    q = request.args.get("q", "")
    suspicious = looks_suspicious(q)
    db = get_db()
    # FIX: parameterized query -- the UNION payload becomes a literal string
    # that matches no message text.
    rows = db.execute(
        "SELECT id, customer_name, email, message FROM contacts WHERE message LIKE ?",
        (f"%{q}%",),
    ).fetchall()
    return render_template(
        "contact.html", q=q, rows=rows, flagged=False, error=None, suspicious=suspicious
    )


# ---------------------------------------------------------------------------
# Scenario 7 (part 2, fixed): stored values are still parameterized on use,
# not just on insert. Trusting "we already saved it safely" is not enough --
# every place a stored value is later used in SQL needs the same care.
# ---------------------------------------------------------------------------
@app.route("/admin/report")
def admin_report():
    db = get_db()
    usernames = [r["username"] for r in db.execute("SELECT username FROM users").fetchall()]
    rows = []
    for uname in usernames:
        # FIX: parameterized even though 'uname' came from our own database --
        # a stored value is still attacker-controlled data if it was ever
        # accepted from user input, which is exactly the case here.
        row = db.execute(
            "SELECT o.status AS activity FROM orders o "
            "JOIN customers c ON o.customer_id = c.id "
            "WHERE c.name = ?",
            (uname,),
        ).fetchone()
        activity = row["activity"] if row else "(no matching activity)"
        rows.append({"username": uname, "activity": activity, "leaked": False})
    return render_template("report.html", rows=rows, error=None)


if __name__ == "__main__":
    # NOTE: debug=False intentionally -- see the matching comment in app.py.
    # host="0.0.0.0" so Docker's published port can reach this process.
    app.run(host="0.0.0.0", port=5010, debug=False)
