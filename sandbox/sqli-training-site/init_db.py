"""
init_db.py
----------
SQLi講義用の教材データベースを毎回まっさらな状態から作り直すスクリプト。

架空の業務システム "Hifumi Secure Shop" 用のシードデータのみを投入する。
実在の個人情報・認証情報は一切含まない。

使い方:
    python init_db.py

app.py (脆弱版) / secure_app.py (修正版) の両方から同じ database.db を参照する。
Docker構成では各コンテナが起動時にこのスクリプトを実行し、毎回クリーンな状態から
デモを始められるようにしている(セカンドオーダーSQLiのデモでデータが汚れるため)。
"""

import os
import sqlite3

DB_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "database.db")

SCHEMA_SQL = """
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS contacts;
DROP TABLE IF EXISTS invite_tokens;

CREATE TABLE users (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    password TEXT NOT NULL,
    role     TEXT NOT NULL,
    email    TEXT NOT NULL
);

CREATE TABLE invite_tokens (
    id    INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT NOT NULL,
    used  INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE products (
    id    INTEGER PRIMARY KEY AUTOINCREMENT,
    name  TEXT NOT NULL,
    price INTEGER NOT NULL,
    stock INTEGER NOT NULL
);

CREATE TABLE customers (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    name              TEXT NOT NULL,
    email             TEXT NOT NULL,
    phone             TEXT NOT NULL,
    last_order_total  INTEGER NOT NULL,
    note              TEXT NOT NULL
);

CREATE TABLE orders (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id    INTEGER NOT NULL,
    tracking_code  TEXT NOT NULL,
    status         TEXT NOT NULL,
    secret_note    TEXT NOT NULL
);

CREATE TABLE contacts (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_name  TEXT NOT NULL,
    email          TEXT NOT NULL,
    message        TEXT NOT NULL,
    internal_memo  TEXT NOT NULL
);
"""

# NOTE: パスワードは講義用に平文で保存している。
# 実運用ではハッシュ化(bcrypt等)必須。絶対に平文保存しないこと。
SEED_USERS = [
    (1, "admin", "admin-secret", "admin", "admin@example.local"),
    (2, "tanaka", "pass1234", "staff", "tanaka@example.local"),
    (3, "sato", "password", "staff", "sato@example.local"),
]

SEED_PRODUCTS = [
    (1, "Premium Keyboard", 19800, 12),
    (2, "Secure Mouse", 4980, 30),
    (3, "Cloud Backup Plan", 1200, 100),
    (4, "CRM License", 30000, 8),
]

SEED_CUSTOMERS = [
    (1, "Alice Demo", "alice@example.local", "090-0000-0001", 24800, "VIP customer"),
    (2, "Bob Sample", "bob@example.local", "090-0000-0002", 4980, "Needs follow-up"),
    (3, "Chika Test", "chika@example.local", "090-0000-0003", 31200, "Internal demo data"),
]

SEED_ORDERS = [
    (1, 1, "ORD-1001", "shipped", "gift option enabled"),
    (2, 2, "ORD-1002", "preparing", "payment confirmed"),
    (3, 3, "ORD-1003", "delayed", "call customer before shipping"),
]

SEED_CONTACTS = [
    (1, "Alice Demo", "alice@example.local", "Please send invoice.", "High priority"),
    (2, "Bob Sample", "bob@example.local", "I want to change my plan.", "Sales follow-up"),
    (3, "Chika Test", "chika@example.local", "Delivery date question.", "Demo memo"),
]

# Fictitious invitation tokens. In this training app the token is checked
# against invite_tokens via a vulnerable query in app.py (fixed in
# secure_app.py). No token value here corresponds to any real system.
SEED_INVITE_TOKENS = [
    (1, "INVITE-2026-STAFF-A1", 0),
    (2, "INVITE-2026-STAFF-A2", 0),
]


def main():
    if os.path.exists(DB_PATH):
        os.remove(DB_PATH)

    conn = sqlite3.connect(DB_PATH)
    try:
        conn.executescript(SCHEMA_SQL)
        conn.executemany(
            "INSERT INTO users (id, username, password, role, email) VALUES (?, ?, ?, ?, ?)",
            SEED_USERS,
        )
        conn.executemany(
            "INSERT INTO products (id, name, price, stock) VALUES (?, ?, ?, ?)",
            SEED_PRODUCTS,
        )
        conn.executemany(
            "INSERT INTO customers (id, name, email, phone, last_order_total, note) VALUES (?, ?, ?, ?, ?, ?)",
            SEED_CUSTOMERS,
        )
        conn.executemany(
            "INSERT INTO orders (id, customer_id, tracking_code, status, secret_note) VALUES (?, ?, ?, ?, ?)",
            SEED_ORDERS,
        )
        conn.executemany(
            "INSERT INTO contacts (id, customer_name, email, message, internal_memo) VALUES (?, ?, ?, ?, ?)",
            SEED_CONTACTS,
        )
        conn.executemany(
            "INSERT INTO invite_tokens (id, token, used) VALUES (?, ?, ?)",
            SEED_INVITE_TOKENS,
        )
        conn.commit()
    finally:
        conn.close()

    print(f"[init_db] Training database created at: {DB_PATH}")


if __name__ == "__main__":
    main()
