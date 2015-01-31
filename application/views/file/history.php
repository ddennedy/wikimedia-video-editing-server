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
<h3><?= $subheading ?></h3>
<?php if (!empty($comment)) echo '<p>' . tr('change_comment') . ": $comment</p>"; ?>
<dl>
<?php foreach($changes as $key => $value): ?>
<?php if (array_key_exists($key, $current)): ?>
<dt><?= tr('file_' . $key) ?></dt>
<?php if ($value !== null): ?>
<dd><code><b>-</b></code> <?= $value ?></dd>
<?php endif ?>
<dd><code>+</code> <?= $current[$key] ?></dd>
<?php endif ?>
<?php endforeach ?>
</dl>
<?php if ($isRestorable): ?>
<hr>
<a href="<?= site_url("file/restore/$id") ?>"><?= tr('restore') ?></a>
<?php endif; ?>
<?php if ($isDownloadable): ?>
<hr>
<a href="<?= site_url("file/download_history/$file_history_id") ?>"><?= tr('download') ?></a>
<?php endif; ?>
