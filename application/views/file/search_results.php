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
<link rel="stylesheet" href="<?= base_url('js/select2/select2.css') ?>" type="text/css" />
<div id="advanced-search" style="margin-top:1em">
    <p><?= tr('search_advanced_heading') ?></p>
    <?= form_open('file/search') ?>
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
            <label for="date_from"><?= tr('file_recording_date') ?></label>
            <input name="date_from" class="datepicker" maxlength="255"
             value="<?= set_value('date_from', $date_from) ?>"
             placeholder="<?= tr('date_from_placeholder') ?>">
            <input name="date_to" class="datepicker" maxlength="255"
             value="<?= set_value('date_to', $date_to) ?>"
             placeholder="<?= tr('date_to_placeholder') ?>">
        </div>

        <div class="field">
            <label for="language"><?= tr('file_language') ?></label>
            <?= form_dropdown('language', $languages, set_value('language', $language)) ?>
        </div>

        <div class="field">
            <label for="license"><?= tr('file_license') ?></label>
            <?= form_dropdown('license', $licenses, set_value('license', $license)) ?>
        </div>

        <div class="action-bar">
            <input class="button" type="submit" name="submit" value="<?= tr('search') ?>">
            <input class="button" type="reset" name="submit" value="<?= tr('reset') ?>">
        </div>
    </form>
</div>
<h2><?= $heading ?></h2>
<?php if (isset($results) && count($results)): ?>
<?= $this->table->generate($results) ?>
<?php else: ?>
<p><em><?= tr('file_search_results_none') ?></em></p>
<?php endif; ?>
<script src="<?= base_url('js/jquery.min.js') ?>"></script>
<script src="<?= base_url('js/jquery-ui.min.js') ?>"></script>
<script src="<?= base_url('js/select2/select2.min.js') ?>"></script>
<?php if (config_item('language') != 'en'): ?>
<script src="<?= base_url('js/i18n/datepicker-' . config_item('language') . '.js') ?>"></script>
<script src="<?= base_url('js/i18n/select2_locale_' . config_item('language') . '.js') ?>"></script>
<?php endif; ?>
<script>
    $(document).ready(function() {
        $('#advanced-search').accordion({
            collapsible: true,
            active: false,
            animate: 100,
            heightStyle: 'content'
        });
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
    });
</script>
