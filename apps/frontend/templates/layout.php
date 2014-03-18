<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <?php include_http_metas() ?>
    <?php include_metas() ?>

    <?php include_title() ?>
    <!-- Le styles -->
    <style>
    html,body{height:100%}#wrap{min-height:100%;height:auto !important;height:100%;margin:0 auto -60px}#push,#footer{height:60px}#footer{background-color:#f5f5f5}@media(max-width:767px){#footer{margin-left:-20px;margin-right:-20px;padding-left:20px;padding-right:20px}}.container{width:auto;max-width:680px}.container .credit{margin:20px 0}
    </style>

    <?php use_stylesheet('bootstrap.css') ?>
    <?php use_stylesheet('bootstrap-responsive.css') ?>
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
    <?php use_javascript('bootstrap.js') ?>

<link rel="shortcut icon" href="/favicon.ico" />

</head>
<body>

    <div id="wrap">
        <div class="container">
            <?php echo $sf_data->getRaw('sf_content') ?>
        </div>
    </div>

    <div id="footer">
        <div class="container">
        <p class="muted credit">

        </p>
        </div>
    </div>

</body>
</html>
