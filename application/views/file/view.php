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
<?php if ($isEditable): ?>
    <a href="<?= site_url('file/edit/' . $id) ?>"><?= tr('edit') ?></a>
<?php endif; ?>
<?php if ($isDeletable): ?>
    | <a href="<?= site_url('file/delete/' . $id) ?>"><?= tr('delete') ?></a>
<?php endif; ?>
<?php if ($isDownloadable): ?>
    | <a href="<?= site_url('file/download/' . $id) ?>"><?= tr('download') ?></a>
<?php endif; ?>
<p><small><?= $footer ?></small></p>
<?= $history ?>
<?php if (isset($pagination)) echo $pagination; ?>
