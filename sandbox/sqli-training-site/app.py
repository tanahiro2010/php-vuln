"""
app.py -- Hifumi Secure Shop (VULNERABLE TRAINING BUILD)
=========================================================

This is the deliberately vulnerable half of a SQL injection training pair.
Every query built with an f-string / string concatenation in this file is
INTENTIONALLY UNSAFE and exists only so an instructor can demonstrate SQL
injection against a local, throwaway SQLite database full of fake data.

DO NOT copy the query-building patterns in this file into real projects.
See secure_app.py for the fixed version of every route below, and README.md
for the full list of demo scenarios.

Run:
    python init_db.py
    python app.py
Then open http://127.0.0.1:5000
"""

import os
import sqlite3
import time
from functools import wraps

from flask import Flask, request, session, redirect, url_for, render_template, flash

DB_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "database.db")

app = Flask(__name__)
app.secret_key = "training-demo-vulnerable-secret-key"  # fine for a local teaching app only
# Distinct cookie name so this app doesn't clobber secure_app.py's session
# when both are open on http://127.0.0.1 at the same time (browsers scope
# cookies by domain, not port).
app.config["SESSION_COOKIE_NAME"] = "hifumi_vuln_session"


def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


def login_required(view):
    @wraps(view)
    def wrapper(*args, **kwargs):
        if "user" not in session:
            flash("ログインが必要です。", "warn")
            return redirect(url_for("login"))
        return view(*args, **kwargs)
    return wrapper


def admin_required(view):
    @wraps(view)
    def wrapper(*args, **kwargs):
        if "user" not in session:
            flash("ログインが必要です。", "warn")
            return redirect(url_for("login"))
        if session.get("role") != "admin":
            flash("この画面は管理者専用です。", "warn")
            return redirect(url_for("dashboard"))
        return view(*args, **kwargs)
    return wrapper


@app.context_processor
def inject_mode():
    return {"MODE_LABEL": "VULNERABLE TRAINING MODE", "MODE_CLASS": "vulnerable"}


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
# Scenario 1 & 2: user enumeration + SQLi login bypass
# ---------------------------------------------------------------------------
@app.route("/login", methods=["GET", "POST"])
def login():
    if request.method == "GET":
        return render_template("login.html")

    username = request.form.get("username", "")
    password = request.form.get("password", "")
    db = get_db()

    login_error = None
    user = None
    try:
        # WARNING: This query is intentionally vulnerable for training purposes.
        # Do not use this style in production. String concatenation lets an
        # attacker close the quote early and rewrite the WHERE clause, e.g.
        #   username = admin' --
        #   username = admin' OR '1'='1' --
        query = f"SELECT * FROM users WHERE username = '{username}' AND password = '{password}'"
        user = db.execute(query).fetchone()
    except Exception as e:
        # Broad on purpose: string-concatenated SQL can raise more than just
        # sqlite3.Error (e.g. a NUL byte in the input raises ValueError), and
        # this app must never crash into an unhandled 500 during a demo.
        login_error = f"クエリエラー: {e}"

    if user:
        session["user"] = user["username"]
        session["role"] = user["role"]
        flash(f"Logged in as {user['username']}", "ok")
        return redirect(url_for("dashboard"))

    if login_error is None:
        # Scenario 1: user enumeration via a differing error message.
        # WARNING: This query is intentionally vulnerable for training purposes.
        try:
            exists_query = f"SELECT 1 FROM users WHERE username = '{username}'"
            exists = db.execute(exists_query).fetchone()
        except Exception as e:
            # Broad on purpose: string-concatenated SQL can raise more than
            # just sqlite3.Error (e.g. a NUL byte raises ValueError), and
            # this app must never crash into an unhandled 500 during a demo.
            exists = None
            login_error = f"クエリエラー: {e}"
        if login_error is None:
            login_error = "パスワードが違います" if exists else "そのユーザーは存在しません"

    return render_template("login.html", login_error=login_error, last_username=username)


@app.route("/logout")
def logout():
    session.clear()
    flash("ログアウトしました。", "info")
    return redirect(url_for("index"))


# ---------------------------------------------------------------------------
# Scenario 7 (part 1): registration -- gated by an invite token.
# The token check is INTENTIONALLY vulnerable (string-concatenated SQL) so
# an attacker can bypass it. The INSERT itself uses a safe placeholder --
# the point of the second-order lesson is that even a "safely stored" value
# reintroduces SQLi later when it's read back and concatenated.
# ---------------------------------------------------------------------------
@app.route("/register", methods=["POST"])
def register():
    token = request.form.get("token", "")
    username = request.form.get("username", "").strip()
    password = request.form.get("password", "")
    if not username or not password or not token:
        flash("招待コード・ユーザー名・パスワードをすべて入力してください。", "warn")
        return redirect(url_for("login"))

    db = get_db()
    try:
        # WARNING: This query is intentionally vulnerable for training purposes.
        # A payload like ' OR '1'='1' -- in the token field bypasses the check.
        token_query = (
            f"SELECT 1 FROM invite_tokens WHERE token = '{token}' AND used = 0"
        )
        row = db.execute(token_query).fetchone()
    except Exception as e:
        # Broad on purpose: string-concatenated SQL can raise more than just
        # sqlite3.Error (e.g. a NUL byte raises ValueError).
        flash(f"クエリエラー: {e}", "danger")
        return redirect(url_for("login"))

    if not row:
        flash("招待コードが無効です。", "danger")
        return redirect(url_for("login"))

    db.execute(
        "INSERT INTO users (username, password, role, email) VALUES (?, ?, 'staff', ?)",
        (username, password, f"{username}@example.local"),
    )
    db.commit()
    flash(f"ユーザー '{username}' を登録しました。", "info")
    return redirect(url_for("login"))


@app.route("/dashboard")
@login_required
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
# Scenario 3: UNION-based leak of customer data through product search
# ---------------------------------------------------------------------------
@app.route("/catalog")
@login_required
def catalog():
    q = request.args.get("q", "")
    # Flag based on the raw input, not on inspecting result columns: a real
    # attacker's UNION output looks just like normal rows, which is the point.
    flagged = "union" in q.lower()
    db = get_db()
    rows = []
    error = None
    try:
        # WARNING: This query is intentionally vulnerable for training purposes.
        # Do not use this style in production.
        query = f"SELECT id, name, price FROM products WHERE name LIKE '%{q}%'"
        rows = db.execute(query).fetchall()
    except Exception as e:
        # Broad on purpose: string-concatenated SQL can raise more than just
        # sqlite3.Error (e.g. a NUL byte in the input raises ValueError), and
        # this app must never crash into an unhandled 500 during a demo.
        error = f"{e}"
    return render_template("catalog.html", q=q, rows=rows, flagged=flagged, error=error)


# ---------------------------------------------------------------------------
# Scenario 4: boolean-based blind SQLi via a numeric id parameter
# ---------------------------------------------------------------------------
@app.route("/customer")
@login_required
def customer_detail():
    raw_id = request.args.get("id", "1")
    db = get_db()
    customer = None
    error = None
    try:
        # WARNING: This query is intentionally vulnerable for training purposes.
        # 'id' is placed directly into a numeric context with no quoting and
        # no validation, so payloads like "1 AND 1=1" / "1 AND 1=2" change
        # the WHERE clause itself (classic boolean-based blind SQLi).
        query = f"SELECT id, name, email, phone, note FROM customers WHERE id = {raw_id}"
        customer = db.execute(query).fetchone()
    except Exception as e:
        # Broad on purpose: string-concatenated SQL can raise more than just
        # sqlite3.Error (e.g. a NUL byte in the input raises ValueError), and
        # this app must never crash into an unhandled 500 during a demo.
        error = f"{e}"
    return render_template("customer_detail.html", raw_id=raw_id, customer=customer, error=error)


# ---------------------------------------------------------------------------
# Scenario 5: pseudo time-based blind SQLi
# ---------------------------------------------------------------------------
@app.route("/track")
@login_required
def track_order():
    raw_order_id = request.args.get("order_id", "1")

    # PSEUDO-DEMO: SQLite has no SLEEP()/pg_sleep(). A real time-based-blind
    # payload against MySQL/Postgres would trigger a genuine DB-side delay;
    # here we simulate the same *observable effect* in Python so the class
    # can see the response-time difference without needing a different DB.
    delayed = False
    if "sleep" in raw_order_id.lower():
        time.sleep(3)
        delayed = True

    db = get_db()
    order = None
    error = None
    try:
        # WARNING: This query is intentionally vulnerable for training purposes.
        query = f"SELECT id, tracking_code, status FROM orders WHERE id = {raw_order_id}"
        order = db.execute(query).fetchone()
    except Exception as e:
        # Broad on purpose: string-concatenated SQL can raise more than just
        # sqlite3.Error (e.g. a NUL byte in the input raises ValueError), and
        # this app must never crash into an unhandled 500 during a demo.
        error = f"{e}"
    return render_template(
        "order_track.html", raw_order_id=raw_order_id, order=order, error=error, delayed=delayed
    )


# ---------------------------------------------------------------------------
# Scenario 6: UNION-based leak of internal_memo through contact search
# ---------------------------------------------------------------------------
@app.route("/contacts")
@login_required
def contacts():
    q = request.args.get("q", "")
    flagged = "union" in q.lower()
    db = get_db()
    rows = []
    error = None
    try:
        # WARNING: This query is intentionally vulnerable for training purposes.
        # Do not use this style in production.
        query = f"SELECT id, customer_name, email, message FROM contacts WHERE message LIKE '%{q}%'"
        rows = db.execute(query).fetchall()
    except Exception as e:
        # Broad on purpose: string-concatenated SQL can raise more than just
        # sqlite3.Error (e.g. a NUL byte in the input raises ValueError), and
        # this app must never crash into an unhandled 500 during a demo.
        error = f"{e}"
    return render_template("contact.html", q=q, rows=rows, flagged=flagged, error=error)


# ---------------------------------------------------------------------------
# Scenario 7 (part 2): second-order SQLi. The username was stored safely by
# /register above; the vulnerability only fires here, when it is read back
# and rebuilt into a new query with string concatenation.
# ---------------------------------------------------------------------------
KNOWN_STATUSES = {"shipped", "preparing", "delayed"}


@app.route("/admin/report")
@admin_required
def admin_report():
    db = get_db()
    usernames = [r["username"] for r in db.execute("SELECT username FROM users").fetchall()]
    rows = []
    for uname in usernames:
        activity = "(no matching activity)"
        leaked = False
        try:
            # WARNING: This query is intentionally vulnerable for training purposes.
            # 'uname' looks safe -- it only ever entered the database through
            # the parameterized INSERT in /register -- but trusting a
            # previously-stored value and concatenating it here reintroduces
            # SQL injection. This is a *second-order* injection: the payload
            # does nothing at registration time and only fires here.
            query = (
                "SELECT o.status AS activity FROM orders o "
                "JOIN customers c ON o.customer_id = c.id "
                f"WHERE c.name = '{uname}'"
            )
            row = db.execute(query).fetchone()
            if row is not None:
                activity = row["activity"]
                leaked = activity not in KNOWN_STATUSES
        except Exception as e:
            # Broad on purpose: string-concatenated SQL can raise more than
            # just sqlite3.Error (e.g. a NUL byte raises ValueError), and
            # this app must never crash into an unhandled 500 during a demo.
            activity = f"クエリエラー: {e}"
        rows.append({"username": uname, "activity": activity, "leaked": leaked})
    return render_template("report.html", rows=rows, error=None)


if __name__ == "__main__":
    # NOTE: debug=False intentionally. Werkzeug's interactive debugger console
    # (enabled by debug=True) is itself a remote-code-execution surface if an
    # unhandled exception is ever reached -- unrelated to the SQLi lessons
    # this app teaches, and out of scope for a training tool. Errors are
    # handled explicitly instead (see the try/except blocks and the
    # errorhandler functions above).
    # NOTE: host="0.0.0.0" so Docker's published port can actually reach this
    # process (binding to 127.0.0.1 only accepts connections from inside the
    # container's own network namespace, which breaks `docker compose up`).
    app.run(host="0.0.0.0", port=5000, debug=False)
