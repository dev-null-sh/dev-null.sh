<?php

declare(strict_types=1);

return [
    'title' => '設定',

    'tabs' => [
        'profile' => 'プロフィール',
        'appearance' => '表示',
        'terminal' => 'ターミナル',
        'safety' => '安全',
    ],

    'appearance' => [
        'title' => '表示設定',
        'theme' => 'テーマ',
        'theme_description' => '「システム」はOSの設定に従います。そのように設定していれば、昼はライト、夜はダークになります。',
        'light' => 'ライト',
        'dark' => 'ダーク',
        'system' => 'システム',
        'who_to_follow' => 'おすすめユーザー',
        'who_to_follow_description' => 'フォローしたくなりそうな3人を右側の列に表示します。小さい画面ではログ内に表示されます。',
        'show' => '表示',
        'hide' => '非表示',
    ],

    'language' => [
        'title' => '言語',
        'description' => 'devnull の表示言語です。コミットは書かれた言語のまま表示されます。',
    ],

    'profile' => [
        'title' => 'プロフィール設定',
        'information' => 'プロフィール情報',
        'information_description' => 'サインイン時に GitHub から反映されます。変更するには GitHub で変更してから、ここで同期してください。',
        'edit_on_github' => 'GitHub で編集',
        'sync' => 'GitHub から同期',
        'view' => 'プロフィールを表示',
        'last_synced' => '最終同期',
        'last_synced_at_sign_in' => '最終同期はサインイン時',
        'just_now' => 'たった今',
        'export' => 'データをエクスポート',
        'export_description' => '保存されているあなたのデータをすべてダウンロードします：プロフィール、コミットと返信（審査待ち、却下、削除済みのものを含む）、スター、保存済みコミット、フォロー、ミュート、ブロック、通知、SSH キーのフィンガープリント、アクティブなセッション。',
        'export_note' => '他の人の返信は含まれません。エクスポートは JSON ファイルです。プロフィールは引き続き GitHub で管理されます。',
        'download_json' => 'JSON をダウンロード',
    ],

    'lane' => [
        'title' => 'カラー',
        'description' => 'グラフ上のあなたのレーンです。コミットの横のレールとノード、アバターの周りのリング、ハンドルの前のドットに使われます。「自動」はアカウント ID から色を選びます。',
        'auto' => '自動',
        'automatic' => '自動（:color）',
        'names' => [
            'orange' => 'オレンジ',
            'green' => 'グリーン',
            'violet' => 'バイオレット',
            'teal' => 'ティール',
            'rose' => 'ローズ',
            'ochre' => 'オーカー',
        ],
        'save' => 'カラーを保存',
    ],

    'pasteis' => [
        'title' => 'Pastéis モード',
        'description' => '毎日届く、ささやかな「いいね」。他の人のコミットに贈れる pastel が毎日10個もらえます。1クリックで1個です。00:00 UTC にリセットされます。',
        'off_note' => 'オフにすると、pastel のボタンやカウンターはすべて非表示になります。他の人からの pastel は引き続き受け取れます。',
        'save_failed' => 'Pastéis モードを保存できませんでした。もう一度お試しください。',
    ],

    'delete_account' => [
        'title' => 'アカウントを削除',
        'description' => 'あなたの /dev/null アカウント、これまでのすべてのコミットと返信、フォローをすべて削除します。あなたのコミットに他の人が付けた返信も削除されます。あなたの返信への返信は、[deleted] の墓標の下に残ります。GitHub アカウントには影響しません。',
        'confirm_title' => 'アカウントを削除しますか？',
        'confirm_description' => 'プロフィール、コミット、返信、フォローは、あなたのコミットに他の人が付けたすべての返信とともに /dev/null へ送られます。あなたの返信への返信は、[deleted] の墓標の下に残ります。この操作は元に戻せません。後で GitHub で再度サインインできますが、ゼロからのスタートになります。',
        'keep' => 'アカウントを残す',
    ],

    'safety' => [
        'title' => '安全設定',
        'muted' => 'ミュート中',
        'muted_description' => '相手のコミットや返信は、ログ、スレッド、検索、おすすめに表示されなくなり、相手からの通知も届きません。相手には知らされず、相手のプロフィールは引き続き閲覧できます。',
        'muted_empty' => 'まだ誰もミュートしていません。',
        'unmute' => 'ミュートを解除',
        'blocked' => 'ブロック中',
        'blocked_description' => 'お互いにフォロー、返信、スターができなくなり、相手のコミットも見えなくなります。ブロックを解除しても、フォローは元に戻りません。',
        'blocked_empty' => 'まだ誰もブロックしていません。',
        'unblock' => 'ブロックを解除',
    ],

    'terminal' => [
        'title' => 'ターミナル設定',
        'from_terminal' => 'ターミナルからコミット',
        'from_terminal_description' => 'ログインもトークンも不要です。スクリプトが GitHub アカウントに登録された SSH キーでコミットに署名し、/dev/null が GitHub で公開されているあなたのキーと照合します。',
        'command' => 'コマンド',
        'reply_command' => '返信コマンド',
        'key_command' => 'キーコマンド',
        'workflow' => 'ワークフロー',
        'from_actions' => 'GitHub Actions からコミット',
        'from_actions_description' => 'リリースを公開したときにリリースノートを投稿したり、ワークフローが知っている情報を何でも投稿したりできます。署名もモデレーションも同じで、上限も同じく1分あたり10コミットです。',
        'step_generate' => 'CI 専用のキーを生成します。',
        'step_add_key_before' => '公開鍵',
        'step_add_key_middle' => 'を GitHub に',
        'step_add_key_link' => '署名キー',
        'step_add_key_after' => 'として追加します。/dev/null のコミットには署名できますが、コードの push やログインには使えません。',
        'step_save_secret_before' => '秘密鍵',
        'step_save_secret_middle' => ' をリポジトリシークレットとして保存し、名前を',
        'step_save_secret_after' => ' にします。その後、両方のファイルを削除します。',
        'step_add_workflow_before' => 'ワークフローを',
        'step_add_workflow_after' => ' に追加します。',
        'inputs' => [
            'body' => 'コミット本文。3〜4,000文字。',
            'ssh_key' => 'シークレットから渡す秘密鍵。パスフレーズなしの Ed25519 または RSA。',
            'reply_to' => '任意。返信先のコミットのハッシュまたはリンク。',
        ],
        'job_output' => 'ジョブはコミットへのリンクと、審査待ちかどうかを出力します。/dev/null に拒否された場合、ジョブは理由とともに失敗します。',
    ],

    'scheduled' => [
        'title' => '予約済みのコミット',
        'back_to_log' => 'ログに戻る',
        'poll_options' => '投票の選択肢',
        'images' => '画像:count枚|画像:count枚',
        'private' => '非公開',
        'intro' => 'この一覧はあなたにしか表示されません。予約した時刻になると、各コミットは通常の審査に回ります。予約は承認ではありません。',
        'times_in' => '時刻は :timezone で表示',
        'empty_title' => '予約済みのコミットはありません',
        'empty_description' => '作成画面で「予約」を選ぶと、後で送信できます。',
        'status' => [
            'scheduled' => '予約済み',
            'waiting' => '送信待ち',
            'pending' => '審査待ち',
            'approved' => '承認済み',
            'rejected' => '却下',
            'deleted' => '削除済み',
        ],
        'cancel' => 'キャンセル',
        'cancel_label' => ':time に予約されたコミットをキャンセル',
        'canceling' => 'キャンセル中…',
        'due_note' => 'スケジューラーを待っています。送信に失敗した場合は自動的に再試行されます。',
        'private_note' => '送信されるまで非公開です。送信前ならキャンセルできます。',
        'previous' => '前へ',
        'next' => '次へ',
    ],
];
