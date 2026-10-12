@php($siteCurrency = site_currency())
<meta name="site-currency" content="{{ $siteCurrency->code }}" data-symbol="{{ $siteCurrency->symbol }}" data-locale="{{ $siteCurrency->locale }}">
