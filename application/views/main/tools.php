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
<h2><?= tr('menu_tools') ?></h2>
<h3><?= tr('tools_user_mgmt') ?></h3>
<ul>
  <li><a href="<?= site_url('user/list/' . User_model::ROLE_BUREAUCRAT) ?>">
    <?= tr('tools_bureaucrats') ?></a></li>
  <li><a href="<?= site_url('user/list/' . User_model::ROLE_ADMIN) ?>">
    <?= tr('tools_administrators') ?></a></li>
  <li><a href="<?= site_url('user/list') ?>">
    <?= tr('tools_list_users') ?></a></li>
  <li><a href="<?= site_url('user/list/' . User_model::ROLE_GUEST) ?>">
    <?= tr('tools_list_guests') ?></a></li>
  <li><form action="<?= site_url('user') ?>"><?= tr('tools_lookup_user') ?>
    <input type="text" name="name" placeholder="<?= tr('tools_username_placeholder') ?>"><input
    type="submit" value="<?= tr('go') ?>"></form></li>
</ul>
