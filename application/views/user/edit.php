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
<?= validation_errors(); ?>

<?php echo form_open('user/edit') ?>
    <label for="role"><?= tr('user_role') ?></label>
    <?= form_dropdown('role', $roles, set_value('role', $role), $roleAttributes) ?><br>

    <label for="language"><?= tr('user_language') ?></label>
    <?= form_dropdown('language', $languages, set_value('language', $language)) ?><br>

    <label for="comment"><?= tr('user_comment') ?></label>
    <textarea name="comment" rows="10" cols="60"><?= set_value('comment', $comment, true) ?></textarea><br>

    <input type="submit" name="submit" value="<?= tr('save') ?>">
    &nbsp;
    <a href="<?= site_url('user/' . $session['username']) ?>"><?= tr('cancel') ?></a>
</form>
