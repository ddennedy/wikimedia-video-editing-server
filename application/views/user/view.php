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
<p><?= $comment ?></p>
<hr>
<?php if ($isEditable): ?>
<a href="<?= site_url('user/edit/' . $name) ?>"><?= tr('edit') ?></a>
<?php endif;
if (!empty($access_token)): ?>
| <a href="https://commons.wikimedia.org/wiki/User:<?= rawurlencode($name) ?>">
    <?= tr('wikimedia_commons') . ' ' . tr('user_name') ?></a>
<?php endif;
if (empty($s3_access_key)): ?>
| <a href="<?= site_url('user/login_archive') ?>">
    <?= tr('s3_connect_action') ?></a>
<?php endif;
if (count($files)) echo $this->table->generate($files);
if (isset($this->pagination)) echo $this->pagination->create_links();
?>
<p><small><?= $footer ?></small></p>
