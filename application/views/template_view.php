<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="keywords" content="" />
    <meta name="description" content="" />
    <title><?php echo $title; ?></title>
    <?php
    if(!empty($css) && is_array($css)):
        foreach($css as $v):
            echo "\t<link href=\"".site_url('assets/css')."/{$v}\" rel=\"stylesheet\" type=\"text/css\" />\n";
        endforeach;
    endif;
    ?>
</head>
<body>
    <div id="contents">
        <?php echo $contents; ?>
    </div>
    <div id="footer">
    </div>
</body>
</html>