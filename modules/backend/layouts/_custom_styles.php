<?php
    use Backend\Models\BrandSetting;
    use Backend\Models\EditorSetting;
?>
<?php if (BrandSetting::isConfigured() || BrandSetting::isBaseConfigured()): ?>
    <style>
        <?= BrandSetting::renderCss() ?>
    </style>
<?php endif ?>
<?php if (EditorSetting::isConfigured() || EditorSetting::isBaseConfigured()): ?>
    <style>
        <?= EditorSetting::renderCss() ?>
    </style>
<?php endif ?>
