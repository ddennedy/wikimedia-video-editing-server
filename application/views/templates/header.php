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
?><!DOCTYPE html>
<html>
  <head>
    <title><?= tr('page_title') ?></title>
  </head>
  <body>
    <h1><?= tr('site_title') ?></h1>
    <hr>
    <form>
    <?php if (isset($session['username'])): ?>
        <?php if (element('role', $session) != User_model::ROLE_GUEST): ?>
            <a href="<?= site_url('user/' . $session['username']) ?>"><?= $session['username'] ?></a> /
        <?php else: ?>
            <?= $session['username'] ?> /
        <?php endif; ?>
        <a href="<?= site_url('user/logout') ?>"><?= tr('menu_logout') ?></a> |
    <?php else: ?>
        <a href="<?= site_url('user/login') ?>"><?= tr('menu_login') ?></a> |
    <?php endif; ?>
    <?php if (element('role', $session) != User_model::ROLE_GUEST): ?>
    <a href="<?= site_url('file/edit') ?>"><?= tr('menu_upload') ?></a> |
    <?php endif; ?>
    <a href="<?= site_url('main') ?>"><?= tr('menu_main') ?></a> |
    <?php if (element('role', $session) == User_model::ROLE_BUREAUCRAT): ?>
    <a href="<?= site_url('main/tools') ?>"><?= tr('menu_tools') ?></a> |
    <?php endif; ?>
    <a href="<?= site_url('file/recent') ?>"><?= tr('menu_recent') ?></a> |
    <input type="text" placeholder="<?= tr('search') ?>"><input type="submit" value="<?= tr('go') ?>">
    </form>
    <hr>
