<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $doc['title'] }}</title>
    <style>
        @page { margin: 18mm 17mm; }
        body { max-width: 900px; margin: 0 auto; padding: 30px 34px 70px; color: #19332a; background: #fff; @if ($format === 'pdf') font-family: sun-exta; @else font-family: system-ui, "Microsoft YaHei", sans-serif; @endif font-size: 14px; line-height: 1.65; }
        h1 { margin: 8px 0 10px; color: #153e2c; font-size: 30px; line-height: 1.25; }
        h2 { margin: 34px 0 11px; padding-top: 13px; border-top: 1px solid #d7e3db; font-size: 20px; }
        h3 { margin: 22px 0 7px; font-size: 16px; }
        p { margin: 8px 0; }
        .eyebrow { color: #317254; font-size: 11px; letter-spacing: 2px; font-weight: 700; }
        .intro { color: #4f665a; font-size: 15px; }
        .meta { margin: 18px 0 26px; padding: 14px 17px; background: #eef5ef; border-left: 3px solid #39845f; }
        .meta code { overflow-wrap: anywhere; }
        .example, .panel { margin: 17px 0; padding: 14px 17px; border: 1px solid #d7e3db; border-radius: 8px; }
        .panel { page-break-inside: avoid; }
        .example h3 { margin-top: 0; }
        .code-title { color: #527262; font-size: 12px; font-weight: 700; margin: 12px 0 4px; }
        pre { margin: 0 0 10px; padding: 12px 14px; color: #143527; background: #eef3ef; border-radius: 6px; @if ($format === 'pdf') font-family: sun-exta; @else font-family: Consolas, 'Courier New', monospace; @endif font-size: 10px; line-height: 1.5; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; }
        code { @if ($format === 'pdf') font-family: sun-exta; @else font-family: Consolas, 'Courier New', monospace; @endif font-size: .9em; }
        table { width: 100%; border-collapse: collapse; margin: 11px 0 20px; }
        th, td { border-bottom: 1px solid #e1eae2; padding: 8px 9px; vertical-align: top; text-align: left; }
        th { background: #e9f2ea; color: #355a45; }
        td:first-child { white-space: nowrap; }
        tr { page-break-inside: avoid; }
        ul { padding-left: 22px; }
        li { margin: 5px 0; }
        .note { color: #576b5e; }
        .copy { float: right; border: 1px solid #9cb9a6; border-radius: 5px; padding: 3px 9px; background: white; color: #255c40; cursor: pointer; }
        footer { margin-top: 35px; border-top: 1px solid #d7e3db; padding-top: 10px; color: #718078; font-size: 11px; }
        @media print { body { padding: 0; } .copy { display: none; } }
    </style>
</head>
<body>
    <div class="eyebrow">MESSAGE PLATFORM / API V1</div>
    <h1>{{ $doc['title'] }}</h1>
    <p class="intro">{{ $doc['intro'] }}</p>
    <div class="meta">
        <strong>提交接口</strong> <code>POST {{ $doc['apiUrl'] }}</code><br>
        <strong>查询接口</strong> <code>GET {{ $doc['queryUrl'] }}</code><br>
        <strong>鉴权</strong> <code>{{ $doc['headers'][0] }}</code>
    </div>

    <h2>接入与请求示例</h2>
    <p>管理员创建接入系统后，将只显示一次的 API 密钥交给业务系统。业务系统以 JSON 提交任务，保存返回的任务 ID，再查询逐收件人的处理结果。请求头还需包含 <code>{{ $doc['headers'][1] }}</code> 和 <code>{{ $doc['headers'][2] }}</code>。</p>
    @foreach ($doc['examples'] as $index => $example)
        <div class="example">
            <h3>{{ $example['title'] }}</h3>
            <p>{{ $example['description'] }}</p>
            <div class="code-title">JSON 请求体
                @if ($format === 'html') <button class="copy" type="button" data-copy="example-{{ $index }}">复制</button> @endif
            </div>
            <pre id="example-{{ $index }}">{{ $example['body'] }}</pre>
            <div class="code-title">cURL / Bash
                @if ($format === 'html') <button class="copy" type="button" data-copy="curl-{{ $index }}">复制</button> @endif
            </div>
            <pre id="curl-{{ $index }}">{{ $example['curl'] }}</pre>
            <div class="code-title">PowerShell / Windows
                @if ($format === 'html') <button class="copy" type="button" data-copy="powershell-{{ $index }}">复制</button> @endif
            </div>
            <pre id="powershell-{{ $index }}">{{ $example['powershell'] }}</pre>
        </div>
    @endforeach

    <h2>请求字段</h2>
    <table>
        <thead><tr><th>字段</th><th>要求</th><th>说明</th></tr></thead>
        <tbody>
        @foreach ($doc['fields'] as $field)
            <tr><td><code>{{ $field[0] }}</code></td><td>{{ $field[1] }}</td><td>{{ $field[2] }}</td></tr>
        @endforeach
        </tbody>
    </table>
    @if (! empty($doc['extraFields']))
        <h3>待办附加字段</h3>
        <table><thead><tr><th>字段</th><th>要求</th><th>说明</th></tr></thead><tbody>
        @foreach ($doc['extraFields'] as $field)
            <tr><td><code>{{ $field[0] }}</code></td><td>{{ $field[1] }}</td><td>{{ $field[2] }}</td></tr>
        @endforeach
        </tbody></table>
    @endif
    <div class="panel note"><strong>通用约定</strong><ul>@foreach ($doc['commonNotes'] as $note)<li>{{ $note }}</li>@endforeach</ul></div>

    <h2>返回与查询</h2>
    <p>首次提交返回 HTTP <strong>202 Accepted</strong>，表示任务已入队。请保存响应中的 <code>id</code>，之后调用 <code>GET {{ $doc['queryUrl'] }}</code>。查询使用同一 Bearer 密钥，仅能查看当前接入系统自己的任务。</p>
    <div class="code-title">首次提交响应示例</div>
    <pre>{{ $doc['response'] }}</pre>
    <p>任务状态包括 <code>queued</code>、<code>processing</code>、<code>accepted</code>、<code>partial</code>、<code>failed</code>；投递状态包括 <code>queued</code>、<code>submitted</code>、<code>accepted</code>、<code>failed</code>。每位收件人的错误原因在 <code>error</code>。</p>
    @if (! empty($doc['upstream']))
        <h2>门户上游接口映射</h2>
        <p>以下接口由消息平台服务器调用，业务系统只需使用统一 API。站内消息使用 WEBSITE 方式，模板为 <code>{{ $doc['messageTemplate'] }}</code>。</p>
        <table><thead><tr><th>业务动作</th><th>POA 路径</th></tr></thead><tbody>
        @foreach ($doc['upstream'] as $row)
            <tr><td>{{ $row[0] }}</td><td><code>{{ $row[1] }}</code></td></tr>
        @endforeach
        </tbody></table>
    @endif
    @if (! empty($doc['operations']))
        <h2>完成与撤销门户待办</h2>
        <p>仅待办支持状态变更，路径中的业务任务号为创建时提交的 <code>business_task_id</code>，需使用同一接入系统密钥。</p>
        @foreach ($doc['operations'] as $operation)
            <div class="panel"><strong>{{ $operation[0] }} {{ $operation[1] }}</strong><br><code>{{ $operation[2] }}</code><p>{{ $operation[3] }}</p></div>
        @endforeach
    @endif

    <h2>错误处理</h2>
    <table><thead><tr><th>HTTP</th><th>含义</th></tr></thead><tbody>
    @foreach ($doc['errors'] as $error)
        <tr><td><code>{{ $error[0] }}</code></td><td>{{ $error[1] }}</td></tr>
    @endforeach
    </tbody></table>
    @if (! empty($doc['validationResponse']))
        <div class="code-title">参数错误响应示例</div>
        <pre>{{ $doc['validationResponse'] }}</pre>
    @endif
    <p>网络超时或服务错误后，使用原 <code>request_id</code> 重试提交，再按任务 ID 查询；新提醒应使用新编号。</p>

    <h2>投递说明</h2>
    <ul>@foreach ($doc['notes'] as $note)<li>{{ $note }}</li>@endforeach</ul>
    <footer>消息提醒平台 · {{ $doc['title'] }} · API v1</footer>
    @if ($format === 'html')
    <script>
        document.querySelectorAll('[data-copy]').forEach(function (button) {
            button.addEventListener('click', async function () {
                const value = document.getElementById(button.dataset.copy).textContent;
                try {
                    if (!navigator.clipboard || !window.isSecureContext) throw new Error('fallback');
                    await navigator.clipboard.writeText(value);
                } catch (_) {
                    const field = document.createElement('textarea');
                    field.value = value;
                    document.body.appendChild(field);
                    field.select();
                    const copied = document.execCommand('copy');
                    field.remove();
                    if (!copied) { alert('复制失败，请选中示例后按 Ctrl+C。'); return; }
                }
                button.textContent = '已复制';
                setTimeout(function () { button.textContent = '复制'; }, 2000);
            });
        });
    </script>
    @endif
</body>
</html>
