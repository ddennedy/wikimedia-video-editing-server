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
<h2><?= $heading ?></h2>
<p><?= $description ?></p>
<p><?= $metadata ?></p>
<p><?php if (isset($missing)) echo $missing; ?></p>
<p><?php if (isset($relations)) echo $relations; ?></p>
<hr>
    <div id="action-bar">
<?php if ($isEditable): ?>
        <a href="<?= site_url('file/edit/' . $id) ?>"><?= tr('edit') ?></a>
<?php endif; ?>
<?php if ($isDeletable): ?>
        | <a href="<?= site_url('file/delete/' . $id) ?>"><?= tr('delete') ?></a>
<?php endif; ?>
<?php if ($isDownloadable): ?>
        | <a href="<?= site_url('file/download/' . $id) ?>"><?= tr('download') ?></a>
<?php endif; ?>
<?php if ($isProjectDownloadable): ?>
        | <a href="<?= site_url('file/download_project/' . $id) ?>"><?= tr('file_download_project') ?></a>
<?php endif; ?>
    </div>

<?php if ($isEditable && $isProject): ?>
    <link rel="stylesheet" href="<?= base_url('css/jquery.fileupload-ui.css') ?>">
    <!-- CSS adjustments for browsers with JavaScript disabled -->
    <noscript><link rel="stylesheet" href="<?= base_url('css/jquery.fileupload-ui-noscript.css') ?>"></noscript>
    <p><?= form_open_multipart('file/view/' . $id) ?>
        <div style="margin-top:1em">
            <span id="upload-button" class="fileinput-button" style="float:inherit">
                <span><?= $upload_button_text ?></span>
                <input id="fileupload" type="file" name="files">
            </span>
            <span id="cancel-button" type="reset" style="display:none">
                <span><?= tr('cancel') ?></span>
            </span>
            <p class="fileupload-progress">
                <div id="progress-bar" class="ui-corner-all"></div>
            </p>
        </div>
        <div id="status" style="clear:both"></div>
        <input id="save-button" type="submit" name="submit" style="display:none">
    </form></p>
    <script src="<?= base_url('js/jquery.min.js') ?>"></script>
    <script src="<?= base_url('js/jquery-ui.min.js') ?>"></script>
    <script src="<?= base_url('js/jquery.iframe-transport.js') ?>"></script>
    <script src="<?= base_url('js/jquery.fileupload.js') ?>"></script>
    <script>
    $(document).ready(function() {
        $('#upload-button').button();
        $('#cancel-button').button();
        $('#fileupload').fileupload({
            url: '<?= site_url('upload/index/' . $id) ?>',
            dataType: 'json',
            add: function(e, data) {
                $('#upload-button').hide();
                $('#cancel-button').show();
                $('#status').text('');
                $('#action-bar').hide();
                jqXHR = data.submit();
            },
            done: function (e, data) {
                $.each(data.result.files, function (index, file) {
                    $('#progress-bar').hide();
                    $('#cancel-button').hide();
                    if (file.error) {
                        $('#upload-button').show();
                        $('#cancel-link').show();
                        html = '<?= str_replace("\n", '', config_item('error_prefix')) ?>';
                        html += file.error;
                        html += '<?= str_replace("\n", '', config_item('error_suffix')) ?>';
                        $('<p/>').html(html).appendTo($('#status'));
                    } else {
                        html = '<?= str_replace("\n", '', config_item('highlight_prefix')) ?>';
                        html += "<?= tr('file_upload_success') ?>";
                        html += '<?= str_replace("\n", '', config_item('highlight_suffix')) ?>';
                        $('<p/>').html(html).appendTo($('#status'));
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
            $('#action-bar').show();
        });
    })
    </script>
<?php endif; ?>

<p><small><?= $footer ?></small></p>
<?= $history ?>
<?php if (isset($pagination)) echo $pagination; ?>
