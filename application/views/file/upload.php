<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * Wikimedia Video Editing Server
 * Copyright (C) 2014 Dan R. Dennedy <dan@dennedy.org>
 * Copyright (C) 2014 CDC Leuphana University Lueneburg
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
?>
<link rel="stylesheet" href="<?= base_url('css/jquery.fileupload-ui.css') ?>">
<!-- CSS adjustments for browsers with JavaScript disabled -->
<noscript><link rel="stylesheet" href="<?= base_url('css/jquery.fileupload-ui-noscript.css') ?>"></noscript>

<h2><?= $heading ?></h2>
<form id="fileupload" action="<?= site_url('file/upload/' . $id) ?>" method="POST" enctype="multipart/form-data">        <!--<div class="fileupload-buttonbar">-->
    <button id="upload-button" class="fileinput-button">
        <span><?= tr('menu_upload') ?></span>
        <input type="file" name="userfile">
    </button>
    <button id="cancel-button" type="reset" style="display:none" >
        <span><?= tr('cancel') ?></span>
    </button>
    <p class="fileupload-progress">
        <div id="progress-bar" class="ui-corner-all"></div>
    </p>
</form>
<div id="status" style="clear:both"></div>

<script src="<?= base_url('js/jquery.min.js') ?>"></script>
<script src="<?= base_url('js/jquery-ui.min.js') ?>"></script>
<script src="<?= base_url('js/jquery.iframe-transport.js') ?>"></script>
<script src="<?= base_url('js/jquery.fileupload.js') ?>"></script>
<script>
    var jqXHR;
    $(document).ready(function() {
        $('#upload-button').button();
        $('#cancel-button').button();
        $('#fileupload').fileupload({
            url: '<?= site_url('file/upload/' . $id) ?>',
            dataType: 'json',
            add: function(e, data) {
                $('#upload-button').hide();
                $('#cancel-button').show();
                $('#status').text('');
                jqXHR = data.submit();
            },
            done: function (e, data) {
                $.each(data.result.files, function (index, file) {
                    if (file.error) {
                        $('#progress-bar').hide();
                        $('#upload-button').show();
                        $('#cancel-button').hide();
                        html = '<?= str_replace("\n", '', config_item('error_prefix')) ?>';
                        html += file.error;
                        html += '<?= str_replace("\n", '', config_item('error_suffix')) ?>';
                        $('<p/>').html(html).appendTo($('#status'));
                    } else {
                        window.location.href = '<?= site_url('file/' . $id) ?>';
                    }
                });
            },
            progress: function(e, data) {
                var progress = parseInt(data.loaded / data.total * 100, 10);
                $('#progress-bar').css('width', progress + '%');
                $('#progress-bar').html('&nbsp;' + progress + '%');
                $('#progress-bar').show();
            }
        });
        $('#cancel-button').click(function (e) {
            jqXHR.abort();
            $('#progress-bar').hide();
            $('#upload-button').show();
            $('#cancel-button').hide();
        });
    });
</script>
