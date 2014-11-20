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

// Data lists
$lang['role_guest'] = 'Guest';
$lang['role_user'] = 'User';
$lang['role_admin'] = 'Administrator';
$lang['role_bureaucrat'] = 'Bureaucrat';
$lang['lang_en'] = 'English';
$lang['lang_de'] = 'German';

$lang['page_title'] = 'Video Editing Server';

// Some simple action labels
$lang['go'] = 'Go';
$lang['edit'] = 'Edit';
$lang['save'] = 'Save';
$lang['cancel'] = 'Cancel';
$lang['search'] = 'Search';

// Menu items
$lang['menu_login'] = 'Login';
$lang['menu_logout'] = 'Logout';
$lang['menu_upload'] = 'Upload';
$lang['menu_main'] = 'Main Page';
$lang['menu_tools'] = 'Tools';

// Main and extra pages
$lang['main_greeting'] = '<p>Under construction</p>';

// User area
$lang['user_register_button'] = 'Register';
$lang['user_role'] = 'Role';
$lang['user_language'] = 'Language';
$lang['user_comment'] = 'Comment';
$lang['user_error_comment'] = 'The user does not exist.';
$lang['user_error_language'] = 'The password is incorrect.';
$lang['user_register_heading'] = 'Welcome, {username}';
$lang['user_register_body'] = 'Do you want to register on this site as a user?
As a user, you will have permission to upload and edit media files and
projects. If you do not register, you may continue to use the site as a guest
and browse files.';
$lang['user_error_login_heading'] = 'Login Error';
$lang['user_error_oauth_init'] = 'OAuth initiation failed.';
$lang['user_error_oauth_access'] = 'OAuth error receiving access token.';
$lang['user_error_oauth_request'] = 'OAuth request token mismatch.';
$lang['user_error_oauth_verify'] = 'OAuth verify code missing.';
$lang['user_error_heading'] = 'User Error';
$lang['user_error_update'] = 'Failed to update user data.';
$lang['user_error_permission'] = 'You do not have permission to edit this user.';
$lang['user_view_heading'] = 'User: {name} ({role})';
$lang['user_view_footer'] = 'This page was last modified {updated_at} UTC.';
$lang['user_view_nocomment'] = 'No comment.';
$lang['user_list_heading'] = 'List Users';

// Tools area
$lang['tools_user_mgmt'] = 'User Management';
$lang['tools_bureaucrats'] = 'List bureaucrats';
$lang['tools_administrators'] = 'List administrators';
$lang['tools_list_users'] = 'List users';
$lang['tools_list_guests'] = 'List guests';
$lang['tools_lookup_user'] = 'Lookup user: ';
$lang['tools_username_placeholder'] = 'Username';
