{{-- Live chat widget — Smartsupp / Jivo / Chatway via LiveChatManager. --}}
@php
    $chat = app(\App\Services\Communications\LiveChat\LiveChatManager::class)->resolved();
    $provider = $chat['provider'];
    $smartsuppKey = (string) ($chat['credentials']['key'] ?? '');
    $jivoWidgetId = $provider === 'jivo' ? (string) ($chat['credentials']['widget_id'] ?? '') : '';
    $chatwayWidgetId = $provider === 'chatway' ? (string) ($chat['credentials']['widget_id'] ?? '') : '';
@endphp

@if($provider === 'smartsupp' && $smartsuppKey !== '')
<script>
(function() {
  if (window.__tthLiveChatLoaded) return;
  window.__tthLiveChatLoaded = true;
  window._smartsupp = window._smartsupp || {};
  window._smartsupp.key = @json($smartsuppKey);
  window._smartsupp.widget = {
    colors: {
      primary: '#004AC6',
      secondary: '#0B1220'
    }
  };
  window.smartsupp || (function(d) {
    var s, c, o = window.smartsupp = function() { o._.push(arguments); };
    o._ = [];
    s = d.getElementsByTagName('script')[0];
    c = d.createElement('script');
    c.type = 'text/javascript';
    c.charset = 'utf-8';
    c.async = true;
    c.src = 'https://www.smartsuppchat.com/loader.js?';
    s.parentNode.insertBefore(c, s);
  })(document);
})();
</script>
<noscript>Powered by <a href="https://www.smartsupp.com" target="_blank" rel="noopener">Smartsupp</a></noscript>
@elseif($provider === 'jivo' && $jivoWidgetId !== '')
@php
    $jivoId = $jivoWidgetId;
    if (preg_match('#code\.jivosite\.com/(?:script/)?widget/([A-Za-z0-9_-]+)#i', $jivoWidgetId, $m)) {
        $jivoId = $m[1];
    } elseif (preg_match('#(?:jv-id|data-jv-id)=[\'"]?([A-Za-z0-9_-]+)#i', $jivoWidgetId, $m)) {
        $jivoId = $m[1];
    } elseif (preg_match('#widget_id\s*=\s*[\'"]?([A-Za-z0-9_-]+)#i', $jivoWidgetId, $m)) {
        $jivoId = $m[1];
    }
    $jivoId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $jivoId);
@endphp
@if($jivoId !== '')
<script>
(function(){
  if (window.__tthLiveChatLoaded) return;
  window.__tthLiveChatLoaded = true;
  var s = document.createElement('script');
  s.type = 'text/javascript';
  s.async = true;
  s.src = @json('https://code.jivosite.com/script/widget/'.$jivoId);
  var ss = document.getElementsByTagName('script')[0];
  ss.parentNode.insertBefore(s, ss);
})();
</script>
@endif
@elseif($provider === 'chatway' && $chatwayWidgetId !== '')
<script id="chatway" async="true" src="{{ 'https://cdn.chatway.app/widget.js?id='.$chatwayWidgetId }}"></script>
@endif
