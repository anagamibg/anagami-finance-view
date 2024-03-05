<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>3DS</title>
</head>
<body>
<script>
    var reference = @json($reference);

    if (window.parent && window.parent !== window) {
        window.parent.postMessage({worldpay3ds: 'complete', reference: reference}, window.location.origin);
    }
</script>
</body>
</html>
