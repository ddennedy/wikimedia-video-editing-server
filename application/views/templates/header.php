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
    <title><?= $lang['page_title'] ?></title>
  </head>
  <body>
    <h1><?= $lang['page_title'] ?></h1>
    <hr>
    <form>
    <?php if (array_key_exists('username', $session)): ?>
        <a href="<?= site_url('user/' . $session['username']) ?>"><?= $session['username'] ?></a> /
        <a href="<?= site_url('user/logout') ?>"><?= $lang['logout'] ?></a>
    <?php else: ?>
        <a href="<?= site_url('user/oauth-initiate') ?>"><?= $lang['login'] ?></a>
    <?php endif; ?>
    | <?= $lang['upload'] ?> |
    <a href="<?= site_url('main') ?>"><?= $lang['main'] ?></a> |
    <input type="text" value="<?= $lang['search'] ?>"><input type="submit" value="Go">
    </form>
    <hr>