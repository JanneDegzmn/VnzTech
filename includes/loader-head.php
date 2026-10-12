<link rel="stylesheet" href="/VNZ-TECH/assets/css/loader.css">
<link rel="stylesheet" href="/VNZ-TECH/assets/css/vnz_dialog.css?v=<?= filemtime(__DIR__ . '/../assets/css/vnz_dialog.css') ?>">
<script src="/VNZ-TECH/assets/js/vnz-dialog.js" defer></script>
<script>
(function () {
    try {
        var root = document.documentElement;
        root.classList.add('vnz-js', 'vnz-loading');

        setTimeout(function () {
            document.documentElement.classList.remove('vnz-loading');
        }, 1000);
    } catch (e) {}
})();
</script>
