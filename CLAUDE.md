@AGENTS.md

# まちチケ

主催者がイベントと券種を登録し、参加者が Stripe Checkout でチケットを買うアプリ

## このプロジェクトの決まり

- 画面は Livewire 4 の単一ファイルコンポーネント（`resources/views/pages/**/⚡*.blade.php`）で作る。Volt は使わない
- UI は Flux UI の Free 版のコンポーネントだけを使う（Pro 専用は使わない）。無いものは Tailwind で作る
- 文言は日本語。日付は `isoFormat('M月D日(ddd) HH:mm')`、金額は「3,000円」の形式
- 認可は `app/Policies/` のポリシーで行い、画面側では `$this->authorize()` を使う
- 外部サービス（Stripe など）に触るコードは `app/Services/` に閉じ込める。テストではそのクラスをモックする
- 機能を追加したら `tests/Feature/` に Pest テストを追加し、`/check` が通ることを確認する
- 開発コマンドは Sail 経由（`sail artisan ...`、`sail composer ...`、`sail npm ...`）。ホストの php や composer は使わない
- ビューを追加・変更したら `sail npm run build` を実行する（Tailwind がクラスを拾うため）

## 作業の進め方

- 変更の前に、何をどのファイルに書くかを短く説明してから実装する
- 1回の指示で扱う範囲は1機能まで。大きい依頼は分割案を先に出す
- 既存のファイルの書き方に合わせる（同じ階層のファイルを先に読む）
- `.env` の値、API キー、パスワードはコードやコミットに含めない
- 失敗したコマンドは、原因の推測と対処案を1つずつ示してから再実行する

## 設計（Day4 以降に追記していく）

詳細は [データベース設計](doc/design/database.md) と [画面一覧](doc/design/screens.md) を読む

### 用語

- イベント（`events`）: 主催者が登録する催し。`draft`（下書き）/ `published`（公開）
- 券種（`ticket_types`）: イベントごとの「一般」「学生」など。価格・定員・販売期間を持つ
- 注文（`orders`）: 1回の購入。1注文で1イベントだけ。`pending` → `paid`、中断・期限切れは `canceled`
- 注文明細（`order_items`）: 注文の中の券種ごとの枚数と、注文時点の単価
- チケット（`tickets`）: 支払い済みになったとき枚数分発行する1枚ずつの券。固有の `code` を持つ
- 主催者は `events.organizer_id`、注文者は `orders.user_id`（どちらも `users`）

### データの決まり

- ロールと状態は Enum（`UserRole` / `EventStatus` / `OrderStatus`）で扱い、文字列を直接書かない
- 金額は円の整数。注文の金額は `order_items.unit_price` を使い、券種の現在の価格を使わない
- 残り枚数はカラムで持たず「定員 − `pending` と `paid` の注文の枚数の合計」で計算する（一覧では `withSum`）
- 注文と明細は消させない（外部キーは restrict）。注文があるイベント・券種の削除は画面側で先に止める
- チケットは Webhook で注文を `paid` にしたときだけ発行する

### 画面とアクセス

- 公開側（`/`、`/events/{event}`）は未ログインでも見られる。下書きと開催済みは出さない（下書きの詳細は404）
- 主催者の画面は `/organizer/events/...`。自分のイベントだけ操作できる（他人のものは403）
- マイページは `/my/orders/...`。自分の注文だけ見られる（他人のものは403）
- 購入（注文の作成）はログインが必要。主催者も参加者として買える
