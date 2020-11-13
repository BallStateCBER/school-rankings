<?php
use Cake\Core\Configure;
?>
<?php if (!Configure::read('debug')): ?>
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-N02XQVCBMQ"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-N02XQVCBMQ');
    </script>
<?php endif; ?>
