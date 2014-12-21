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
<style>
    div.ui-corner-all {
        width: 16px;
        height: 16px;
        padding: 4px;
    }
</style>
<link rel="stylesheet" href="<?= base_url('js/select2/select2.css') ?>" type="text/css" />

<h2><?= $heading ?></h2>
<p><?= $message ?></p>
<?= validation_errors(); ?>

<?= form_open_multipart('file/edit/' . $id) ?>
    <?php if ($id && empty($source_path)): ?>
    <link rel="stylesheet" href="<?= base_url('css/jquery.fileupload-ui.css') ?>">
    <!-- CSS adjustments for browsers with JavaScript disabled -->
    <noscript><link rel="stylesheet" href="<?= base_url('css/jquery.fileupload-ui-noscript.css') ?>"></noscript>
    <div class="field">
        <label for="files"><?= tr('file_upload') ?></label>
        <span id="upload-button" class="fileinput-button">
            <span><?= $upload_button_text ?></span>
            <input id="fileupload" type="file" name="files">
        </span>
        <span id="cancel-button" type="reset" style="display:none" >
            <span><?= tr('cancel') ?></span>
        </span>
        <p class="fileupload-progress">
            <div id="progress-bar" class="ui-corner-all"></div>
        </p>
    </div>
    <div id="status" style="clear:both"></div>
    <?php endif; ?>

    <div class="field">
        <label for="title"><?= tr('file_title') ?></label>
        <input name="title" maxlength="255" style="width:60%"
         value="<?= set_value('title', $title) ?>">
    </div>

    <div class="field">
        <label for="description"><?= tr('file_description') ?></label>
        <textarea name="description" rows="10" style="width:60%"><?= set_value('description', $description, true) ?></textarea>
    </div>

    <div class="field">
        <label for="author"><?= tr('file_author') ?></label>
        <input name="author" maxlength="255" style="width:60%"
         value="<?= set_value('author', $author) ?>">
     </div>

    <div class="field">
        <label for="keywords"><?= tr('file_keywords') ?></label>
        <input name="keywords" class="select2" maxlength="1000" style="width:60.5%"
         value="<?= set_value('keywords', $keywords) ?>">
    </div>

    <div class="field">
        <label for="recording_date"><?= tr('file_recording_date') ?></label>
        <input name="recording_date" class="datepicker" maxlength="255"
         value="<?= set_value('recording_date', $recording_date) ?>">
    </div>

    <div class="field">
        <label for="language"><?= tr('file_language') ?></label>
        <?= form_dropdown('language', $languages, set_value('language', $language)) ?>
    </div>

    <div class="field">
        <label for="license"><?= tr('file_license') ?></label>
        <?= form_dropdown('license', $licenses, set_value('license', $license)) ?>
    </div>

    <div class="field">
        <label><?= tr('file_properties') ?></label>
        <div class="repeat">
            <table class="wrapper" width="60%">
                <thead>
                    <tr>
                        <th style="width:20%"><?= tr('file_properties_name')?></th>
                        <th style="width:38%"><?= tr('file_properties_value')?></th>
                        <th  style="width:2%" colspan="2"><div class="add ui-state-default ui-corner-all" title="<?= tr('add') ?>"><span class="ui-icon ui-icon-plus"></span></div></th>
                    </tr>
                </thead>
                <tbody class="container">
                    <tr class="template row">
                        <td style="width:20%"><input type="text" name="properties[{{row-count-placeholder}}][name]"  style="width:98%"></td>
                        <td style="width:38%"><input type="text" name="properties[{{row-count-placeholder}}][value]" style="width:99%"></td>
                        <td style="width:1%"><div class="remove ui-state-default ui-corner-all" title="<?= tr('remove') ?>"><span class="ui-icon ui-icon-minus"></span></div></td>
                        <td style="width:1%"><div class="move ui-state-default ui-corner-all" title="<?= tr('move') ?>"><span class="ui-icon ui-icon-arrow-2-n-s"></span></div></td>
                    </tr>
<?php   $i = 0;
        if (isset($properties) && array_key_exists('user', $properties)):
            foreach ($properties['user'] as $p):
?>
                    <tr class="row">
                        <td style="width:20%"><input type="text" name="properties[<?= $i ?>][name]" size="20"  style="width:98%" value="<?= htmlspecialchars($p['name']) ?>"></td>
                        <td style="width:38%"><input type="text" name="properties[<?= $i ?>][value]" size="40" style="width:99%" value="<?= htmlspecialchars($p['value']) ?>"></td>
                        <td style="width:1%"><div class="remove ui-state-default ui-corner-all" title="<?= tr('remove') ?>"><span class="ui-icon ui-icon-minus"></span></div></td>
                        <td style="width:1%"><div class="move ui-state-default ui-corner-all" title="<?= tr('move') ?>"><span class="ui-icon ui-icon-arrow-2-n-s"></span></div></td>
                    </tr>
<?php           $i++;
            endforeach;
        else:
?>
                    <tr class="row">
                        <td style="width:20%"><input type="text" name="properties[<?= $i ?>][name]" size="20"  style="width:98%" value=""></td>
                        <td style="width:38%"><input type="text" name="properties[<?= $i ?>][value]" size="40" style="width:99%" value=""></td>
                        <td style="width:1%"><div class="remove ui-state-default ui-corner-all" title="<?= tr('remove') ?>"><span class="ui-icon ui-icon-minus"></span></div></td>
                        <td style="width:1%"><div class="move ui-state-default ui-corner-all" title="<?= tr('move') ?>"><span class="ui-icon ui-icon-arrow-2-n-s"></span></div></td>
                    </tr>
<?php   endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="action-bar">
        <input id="save-button" class="button" type="submit" name="submit" value="<?= tr('save') ?>">
        <?php if ($id): ?>
        &nbsp;
        <a id="cancel-link" href="<?= site_url('file/' . $id) ?>"><?= tr('cancel') ?></a>
        <?php endif; ?>
    </div>
</form>

<script src="<?= base_url('js/jquery.min.js') ?>"></script>
<script src="<?= base_url('js/jquery-ui.min.js') ?>"></script>
<script src="<?= base_url('js/select2/select2.min.js') ?>"></script>
<script src="<?= base_url('js/repeatable-fields.js') ?>"></script>
<?php if (empty($source_path)): ?>
<script src="<?= base_url('js/jquery.iframe-transport.js') ?>"></script>
<script src="<?= base_url('js/jquery.fileupload.js') ?>"></script>
<?php endif; ?>
<?php if (config_item('language') != 'en'): ?>
<script src="<?= base_url('js/i18n/datepicker-' . config_item('language') . '.js') ?>"></script>
<script src="<?= base_url('js/i18n/select2_locale_' . config_item('language') . '.js') ?>"></script>
<?php endif; ?>
<script>
    $(document).ready(function() {
<?php if (empty($source_path)): ?>
        $('#upload-button').button();
        $('#cancel-button').button();
        $('#fileupload').fileupload({
            url: '<?= site_url('upload/index/' . $id) ?>',
            dataType: 'json',
            maxChunkSize: 10000000, // 10 MB
            uploadedBytes: <?= $size_bytes? $size_bytes : 0 ?>,
            add: function(e, data) {
                $('#upload-button').hide();
                $('#cancel-button').show();
                $('#status').text('');
                $('#save-button').hide();
                $('#cancel-link').hide();
                jqXHR = data.submit();
            },
            done: function (e, data) {
                $.each(data.result.files, function (index, file) {
                    $('#progress-bar').hide();
                    $('#cancel-button').hide();
                    $('#save-button').show();
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
            $('#save-button').show();
            $('#cancel-link').show();
        });
<?php endif; ?>

        $.datepicker.setDefaults($.datepicker.regional["<?= config_item('language') ?>"]);
        $("input.datepicker").datepicker({
            changeYear: true,
            changeMonth: true,
            autoSize: true,
            dateFormat: "yy-mm-dd"
        });
        $("input.select2").select2({
            placeholder: "<?= tr('file_keywords_placeholder') ?>",
            tags: [],
            separator: "\t",
            minimumInputLength: 1,
            initSelection: function (element, callback) {
                var data = [];
                $(element.val().split("\t")).each(function () {
                    data.push({id: this, text: this});
                });
                callback(data);
            },
            ajax: {
                url: "<?= site_url('file/keywords') ?>",
                dataType: 'json',
                quietMillis: 250,
                data: function (term, page) {
                    return { q: term };
                },
                results: function (data, page) {
                    return { results: data };
                },
                cache: true
            }
        });
        $('.repeat').each(function() {
            $(this).repeatable_fields();
        });
    })
</script>
