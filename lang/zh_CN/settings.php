<?php

declare(strict_types=1);

return [
    'title' => '设置',

    'tabs' => [
        'profile' => '个人资料',
        'appearance' => '外观',
        'terminal' => '终端',
        'safety' => '安全',
    ],

    'appearance' => [
        'title' => '外观设置',
        'theme' => '主题',
        'theme_description' => '“跟随系统”会跟随你的操作系统设置，例如白天浅色、夜晚深色（如果你是这样设置的）。',
        'light' => '浅色',
        'dark' => '深色',
        'system' => '跟随系统',
        'who_to_follow' => '推荐关注',
        'who_to_follow_description' => '推荐三位你可能想关注的人，显示在右侧栏；在较小的屏幕上则显示在你的日志中。',
        'show' => '显示',
        'hide' => '隐藏',
    ],

    'language' => [
        'title' => '语言',
        'description' => 'devnull 的显示语言。提交内容会保持其原本的语言。',
    ],

    'profile' => [
        'title' => '个人资料设置',
        'information' => '个人资料信息',
        'information_description' => '登录时从 GitHub 同步。如需修改，请先在 GitHub 上修改，再在这里同步。',
        'edit_on_github' => '在 GitHub 上编辑',
        'sync' => '从 GitHub 同步',
        'view' => '查看个人主页',
        'last_synced' => '上次同步',
        'last_synced_at_sign_in' => '上次同步于你登录时',
        'just_now' => '刚刚',
        'export' => '导出你的数据',
        'export_description' => '下载我们存储的关于你的全部数据：个人资料、提交和回复（包括待审核、已拒绝和已删除的）、星标、收藏的提交、关注、静音、屏蔽、通知、SSH 密钥指纹以及活跃会话。',
        'export_note' => '不包括其他人的回复。导出文件为 JSON 格式。你的个人资料仍由 GitHub 管理。',
        'download_json' => '下载 JSON',
    ],

    'lane' => [
        'title' => '颜色',
        'description' => '你在图谱上的泳道：你的提交旁的轨道和节点、头像周围的圆环，以及用户名前的圆点。“自动”会根据你的账号 ID 选择一种颜色。',
        'auto' => '自动',
        'automatic' => '自动（:color）',
        'names' => [
            'orange' => '橙色',
            'green' => '绿色',
            'violet' => '紫罗兰',
            'teal' => '青色',
            'rose' => '玫瑰红',
            'ochre' => '赭黄',
        ],
        'save' => '保存颜色',
    ],

    'pasteis' => [
        'title' => 'Pastéis 模式',
        'description' => '每天一点小小的赞赏。每天获得 10 个 pastel，可以送给其他人的提交，每次点击送一个。额度每天 00:00 UTC 重置。',
        'off_note' => '关闭后，所有 pastel 按钮和计数都会隐藏。你仍然可以收到别人送的 pastel。',
        'save_failed' => '无法保存 Pastéis 模式，请重试。',
    ],

    'delete_account' => [
        'title' => '删除账号',
        'description' => '删除你的 /dev/null 账号、你发布的所有提交和回复，以及你的全部关注。其他人在你的提交下留下的回复也会一并删除。你的回复下的回复会保留，挂在一个 [deleted] 墓碑之下。你的 GitHub 账号不受影响。',
        'confirm_title' => '删除你的账号？',
        'confirm_description' => '你的个人资料、提交、回复和关注都会被丢进 /dev/null，其他人在你的提交下留下的所有回复也一样。你的回复下的回复会保留，挂在一个 [deleted] 墓碑之下。此操作无法撤销。之后你可以再次使用 GitHub 登录，但一切都要从零开始。',
        'keep' => '保留我的账号',
    ],

    'safety' => [
        'title' => '安全设置',
        'muted' => '已静音',
        'muted_description' => '他们的提交和回复不会出现在你的日志、讨论串、搜索和推荐中，也无法向你发送通知。对方不会收到提示，你仍然可以访问对方的个人主页。',
        'muted_empty' => '你还没有静音任何人。',
        'unmute' => '取消静音',
        'blocked' => '已屏蔽',
        'blocked_description' => '你们双方都无法关注、回复对方或为对方加星标，也看不到对方的提交。取消屏蔽后，之前的关注不会恢复。',
        'blocked_empty' => '你还没有屏蔽任何人。',
        'unblock' => '取消屏蔽',
    ],

    'terminal' => [
        'title' => '终端设置',
        'from_terminal' => '从终端提交',
        'from_terminal_description' => '无需登录，无需令牌：脚本会使用你 GitHub 账号中的 SSH 密钥为提交签名，/dev/null 会用 GitHub 为你公开的密钥进行验证。',
        'command' => '命令',
        'reply_command' => '回复命令',
        'key_command' => '密钥命令',
        'workflow' => '工作流',
        'from_actions' => '从 GitHub Actions 提交',
        'from_actions_description' => '发布 release 时自动发布更新说明，或者任何工作流知道的内容。同样的签名，同样的审核，同样每分钟最多 10 条提交。',
        'step_generate' => '生成一个仅供 CI 使用的密钥。',
        'step_add_key_before' => '将',
        'step_add_key_middle' => '添加到 GitHub，作为',
        'step_add_key_link' => '签名密钥',
        'step_add_key_after' => '。它可以签署 /dev/null 提交，但无法推送代码或登录。',
        'step_save_secret_before' => '将私钥',
        'step_save_secret_middle' => ' 保存为仓库机密，名称为',
        'step_save_secret_after' => '，然后删除这两个文件。',
        'step_add_workflow_before' => '添加工作流，路径为',
        'step_add_workflow_after' => '。',
        'inputs' => [
            'body' => '提交内容，3 到 4,000 个字符。',
            'ssh_key' => '私钥，来自机密。Ed25519 或 RSA，无密码短语。',
            'reply_to' => '可选。要回复的提交的哈希或链接。',
        ],
        'job_output' => '任务会输出你的提交链接，以及它是否在等待审核。如果 /dev/null 拒绝了它，任务会失败并给出原因。',
    ],

    'scheduled' => [
        'title' => '定时提交',
        'back_to_log' => '返回日志',
        'private' => '私密',
        'intro' => '只有你能看到这个列表。到了设定时间，每条提交都会经过常规审核流程。定时不等于审核通过。',
        'times_in' => '时间以 :timezone 显示',
        'empty_title' => '没有定时提交',
        'empty_description' => '在编辑器中选择“定时”，稍后再提交。',
        'status' => [
            'scheduled' => '已定时',
            'waiting' => '等待提交',
            'pending' => '已提交审核',
            'approved' => '已通过',
            'rejected' => '已拒绝',
            'deleted' => '已删除',
        ],
        'cancel' => '取消',
        'cancel_label' => '取消定于 :time 的提交',
        'canceling' => '正在取消…',
        'due_note' => '正在等待调度程序。如果提交失败，会自动重试。',
        'private_note' => '提交前仅自己可见。提交前可以随时取消。',
        'previous' => '上一页',
        'next' => '下一页',
    ],
];
