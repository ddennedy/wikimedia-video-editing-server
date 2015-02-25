<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * Wikimedia Video Editing Server
 * Copyright (C) 2014-2015 Dan R. Dennedy <dan@dennedy.org>
 * Copyright (C) 2014-2015 CDC Leuphana University Lueneburg
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
    <link rel="stylesheet" href="<?= base_url('css/jquery-ui.structure.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/jquery-ui.theme.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
  </head>
  <body>
    <h1><?= tr('site_title') ?></h1>
    <form method="get" action="<?= site_url('file/search') ?>">
    <?php if (isset($session['username'])): ?>
        <?php if (element('role', $session) != User_model::ROLE_GUEST): ?>
            <a href="<?= site_url('user/' . $session['username']) ?>"><?= $session['username'] ?></a> /
        <?php else: ?>
            <?= $session['username'] ?> /
        <?php endif; ?>
        <a href="<?= site_url('user/logout') ?>"><?= tr('menu_logout') ?></a> |
    <?php else: ?>
        <a href="<?= site_url('user/login') ?>"><?= tr('menu_login') ?></a> |
        <?php if (config_item('auth') === User_model::AUTH_LOCAL): ?>
            <a href="<?= site_url('user/register') ?>"><?= tr('user_register_button') ?></a> |
        <?php endif; ?>
    <?php endif; ?>
    <?php if (element('role', $session) != User_model::ROLE_GUEST): ?>
    <a href="<?= site_url('file/edit') ?>"><?= tr('menu_upload') ?></a> |
    <?php endif; ?>
    <a href="<?= site_url('main') ?>"><?= tr('menu_main') ?></a> |
    <a href="<?= site_url('main/tools') ?>"><?= tr('menu_tools') ?></a> |
    <a href="<?= site_url('file/recent') ?>"><?= tr('menu_recent') ?></a> |
    <input type="text" name="q" placeholder="<?= tr('search') ?>"><input type="submit" value="<?= tr('go') ?>">
    </form>
