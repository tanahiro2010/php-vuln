# Hifumi Secure Shop -- SQLインジェクション講義用教材サイト

## 1. 概要

このアプリは **SQLインジェクション講座で講師が実演するためのローカル専用教材** です。
架空の社内CRM / EC管理システム「Hifumi Secure Shop」を模しており、同じ機能を持つ

- **脆弱版**: [`app.py`](app.py) -- `http://127.0.0.1:5000`
- **修正版**: [`secure_app.py`](secure_app.py) -- `http://127.0.0.1:5010`

の2つを同時に起動し、同じ攻撃入力を試して「脆弱版では成功する／修正版では失敗する」ことを
画面上で見比べられるようにしています。

## 2. 注意事項

- **学習目的専用です。** 実在のWebサイト・実在のシステムに対してここで扱う攻撃手法を試さないでください。
- データはすべて架空の教材用データです（`init_db.py` 参照）。実在の個人情報・認証情報・APIキーは一切含まれていません。
- `app.py` は **意図的に脆弱な実装** を含みます。コード中に `WARNING: This query is intentionally vulnerable for training purposes.` というコメントを付けています。
- ユーザーのパスワードは **講義用に平文で保存** しています。**実運用では絶対にNGです**（本来はbcrypt等でハッシュ化してください）。
- セッション管理・認証は講義用の簡易実装です。CSRF対策やアクセス制御の強制は行っていません（各脆弱性シナリオを単体で見せやすくするため、ログインしていなくても各画面を閲覧できるようにしています）。本番品質のセキュリティ実装ではありません。
- 外部ネットワークへの通信・スキャン機能は含まれていません。すべてローカルのSQLite DBのみを対象とします。

## 3. 起動方法

### Pythonで起動（脆弱版）

```bash
python -m venv .venv
source .venv/bin/activate      # Windowsの場合は下記参照
pip install -r requirements.txt
python init_db.py
python app.py
```

Windows (PowerShell) の場合の仮想環境有効化:

```powershell
python -m venv .venv
.venv\Scripts\Activate.ps1
pip install -r requirements.txt
python init_db.py
python app.py
```

Windows (コマンドプロンプト) の場合:

```cmd
python -m venv .venv
.venv\Scripts\activate.bat
pip install -r requirements.txt
python init_db.py
python app.py
```

ブラウザで `http://127.0.0.1:5000` を開いてください。

### 修正版を起動する

同じ仮想環境のまま、別ターミナルで:

```bash
python secure_app.py
```

ブラウザで `http://127.0.0.1:5010` を開いてください。

（`app.py` と `secure_app.py` は同じ `database.db` を参照します。セカンドオーダーSQLiのデモでデータを汚したくない場合は、都度 `python init_db.py` を再実行してリセットしてください。）

### Dockerで起動

```bash
docker compose up --build
```

- 脆弱版: `http://127.0.0.1:5050`（コンテナ内部は5000番のまま。下記の注意を参照）
- 修正版: `http://127.0.0.1:5010`

各コンテナは起動時にそれぞれ独立した `database.db` を作り直すので、Docker版では2つのアプリはDBを共有しません（起動のたびにクリーンな状態からデモできます）。

**macOSでの注意（AirPlay Receiverとのポート衝突）**: macOSは標準で「AirPlay Receiver」機能がホストの5000番ポート（と7000番）を掴んでいることがあり、`docker compose up` が `bind: address already in use` で失敗したり、アクセスしても中身のない `403 Forbidden`（`Server: AirTunes/...`）が返ってくることがあります。これを避けるため、`docker-compose.yml` では脆弱版のホスト側ポートを `5050` に変更しています。ローカルで直接 `python app.py` を実行する場合（ポート5000）でも同じ衝突が起き得ます。その場合はシステム設定 → 一般 → AirDropとHandoff → 「AirPlay Receiver」をオフにしてください。

## 4. 講義用デモシナリオ

以下は脆弱版 (`http://127.0.0.1:5000`) を対象にした手順です。同じ入力を修正版 (`http://127.0.0.1:5010`) にも試して、失敗することを確認してください（シナリオF）。

### シナリオA: ユーザー列挙

`/login` に以下を入力:

```
存在しないユーザー名 + 適当なパスワード → 「そのユーザーは存在しません」
admin + わざと間違えたパスワード       → 「パスワードが違います」
```

この時点で `admin` というアカウントが実在することが攻撃者に分かってしまいます。

### シナリオB: ログインバイパス

`/login` に以下を入力（パスワード欄は何でもよい）:

```
username: admin' --
password: anything
```

または:

```
username: admin' OR '1'='1' --
password: anything
```

ログインに成功し、ダッシュボードに `Logged in as admin` と表示されます。

### シナリオC: UNIONによる商品検索からの顧客情報漏洩

```
/catalog?q=%' UNION SELECT id, email, last_order_total FROM customers --
```

商品一覧に顧客のメールアドレスと注文金額が混ざって表示され、`LEAKED` バッジが付きます。

### シナリオD: 問い合わせ検索からの社内メモ漏洩

```
/contacts?q=%' UNION SELECT id, customer_name, email, internal_memo FROM contacts --
```

本来表示されないはずの `internal_memo`（社内メモ）が一覧に表示されます。

### シナリオE: Boolean-based Blind SQLiの確認

```
/customer?id=1 AND 1=1   → 顧客情報が表示される
/customer?id=1 AND 1=2   → 「該当する顧客が見つかりません」
```

画面に直接データが出なくても、真偽の違いだけで「その条件が真かどうか」を推測できることを説明します。

（発展・任意）疑似 Time-based Blind の確認:

```
/track?order_id=1 sleep
```

応答が約3秒遅れます（SQLiteにはSLEEP()が無いため、講義用にPython側で疑似再現しています。詳細は `app.py` の `track_order` 内コメント参照）。

### シナリオ: セカンドオーダーSQLi

1. `/login` 画面下部の「新規ユーザー登録」フォームで、以下のユーザー名を登録する:
   ```
   nobody' UNION SELECT password FROM users WHERE username='admin' --
   ```
   （パスワードは何でも可）登録時には何も異常は起きません。
2. `/admin/report`（管理者レポート）を開く。
3. 登録した「ユーザー名」の行の "Latest Activity" 列に、`admin` の平文パスワード（`admin-secret`）が表示され、`LEAKED` バッジが付きます。

「入力した瞬間は安全に見えても、後でその値を別の場所で使うときに脆弱性が発火する」ことを示すデモです。

### シナリオF: 修正版で同じ攻撃が失敗することを確認

`http://127.0.0.1:5010` に切り替えて、シナリオA〜E・セカンドオーダーの入力をすべて同じように試してください。

- ログインバイパス → 失敗し、常に「ユーザー名またはパスワードが正しくありません」
- UNION検索 → 通常の検索結果のみ（該当なしになることが多い）、`LEAKED` バッジは出ない
- `?id=1 AND 1=2` → 400エラー「idは整数で指定してください。」
- セカンドオーダーの悪意あるユーザー名 → レポートには何も漏れず、`(no matching activity)` と表示される

疑わしい入力が検知された場合は、緑色の🛡バナー「疑わしい入力を検知しましたが、プレースホルダにより安全に処理されました」が表示されます。

## 5. 脆弱版と修正版のコード差分

| 機能 | 脆弱版 (`app.py`) | 修正版 (`secure_app.py`) |
| --- | --- | --- |
| ログイン (`/login`) | `f"...WHERE username = '{username}' AND password = '{password}'"` で文字列結合。存在確認クエリでエラーメッセージを出し分け | `?` プレースホルダで `execute(query, (username, password))`。エラーメッセージは常に同一文言 |
| 商品検索 (`/catalog`) | `f"...WHERE name LIKE '%{q}%'"` で文字列結合 → UNIONが通る | `execute("... LIKE ?", (f"%{q}%",))` → UNION文字列はただの検索語として扱われる |
| 顧客詳細 (`/customer`) | `f"...WHERE id = {raw_id}"`（数値コンテキスト、無検証） | `raw_id.isdigit()` で検証 → `execute("... WHERE id = ?", (int(raw_id),))` |
| 注文照会 (`/track`) | `f"...WHERE id = {raw_order_id}"` に加え、`"sleep" in raw_order_id` で疑似遅延 | 同様に整数検証。遅延ロジックは存在しない |
| 問い合わせ検索 (`/contacts`) | `f"...WHERE message LIKE '%{q}%'"` で文字列結合 → UNIONで `internal_memo` 漏洩 | `execute("... LIKE ?", (f"%{q}%",))` |
| 管理者レポート (`/admin/report`) | 登録時は安全（プレースホルダ）だが、レポート生成時に `f"...WHERE c.name = '{uname}'"` で再度文字列結合 → セカンドオーダーSQLi | レポート生成時も `execute(query, (uname,))` でプレースホルダ使用 |

全ての脆弱ポイントには `app.py` 内で `# WARNING: This query is intentionally vulnerable for training purposes.` というコメントを付けています。

## 6. ZAPでのスキャンについて

以下の入力点はすべて通常のHTMLフォーム / GETパラメータとして実装されているため、OWASP ZAPのSpider / Active Scanで見つけやすい構成になっています。

- `/login` のPOSTフォーム（ユーザー名・パスワード）
- `/register` のPOSTフォーム（ユーザー名・パスワード）
- `/catalog?q=...`
- `/customer?id=...`
- `/track?order_id=...`
- `/contacts?q=...`

ZAPでの検出結果は環境やスキャン設定に依存しますが、上記いずれも診断対象として見つけやすい素直な構成にしています。
