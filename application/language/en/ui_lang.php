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
$lang['language_en'] = 'English';
$lang['language_de'] = 'German';
$lang['license_self|GFDL|cc-by-sa-all|migration=redundant'] = 'Own work, copyleft, attribution required (Multi-license GFDL, CC-BY-SA all versions)';
$lang['license_self|Cc-zero'] = 'CC0 1.0 Universal Public Domain Dedication, all rights waived (Public domain)';
$lang['license_PD-self'] = 'Own work, all rights released (Public domain)';
$lang['license_self|GFDL|cc-by-sa-3.0|migration=redundant'] = 'Own work, copyleft, attribution required (GFDL, CC-BY-SA-3.0)';
$lang['license_self|GFDL|cc-by-3.0|migration=redundant'] = 'Own work, attribution required (GFDL, CC-BY 3.0)';
$lang['license_self|cc-by-sa-3.0'] = 'Own work, copyleft, attribution required (CC-BY-SA-3.0)';
$lang['license_cc-by-sa-4.0'] = 'Creative Commons Attribution ShareAlike 4.0';
$lang['license_cc-by-sa-3.0'] = 'Creative Commons Attribution ShareAlike 3.0';
$lang['license_cc-by-4.0'] = 'Creative Commons Attribution 4.0';
$lang['license_cc-by-3.0'] = 'Creative Commons Attribution 3.0';
$lang['license_Cc-zero'] = 'Creative Commons CC0 1.0 Universal Public Domain Dedication';
$lang['license_FAL'] = 'Free Art License';
$lang['license_PD-old-100'] = 'Public Domain: Author died more than 100 years ago';
$lang['license_PD-old-70-1923'] = 'Public Domain: Author died more than 70 years ago AND the work was published before 1923';
$lang['license_PD-old-70|Unclear-PD-US-old-70'] = 'Public Domain: Author died more than 70 years ago BUT the work was published after 1923';
$lang['license_PD-US'] = 'Public Domain: First published in the United States before 1923';
$lang['license-PD-US-no notice'] = 'Public Domain: First published in the United States between 1923 and 1977 without a copyright notice';
$lang['license_PD-USGov'] = 'Public Domain: Original work of the US Federal Government';
$lang['license_PD-USGov-NASA'] = 'Public Domain: Original work of NASA';
$lang['license_PD-USGov-Military-Navy'] = 'Public Domain: Original work of the US Military Navy';
$lang['license_PD-ineligible'] = 'Public Domain: Too simple to be copyrighted';
$lang['license_Copyrighted free use'] = 'Copyrighted, but may be used for any purpose, including commercially';
$lang['license_Attribution'] = 'May be used for any purpose, including commercially, if the copyright holder is properly attributed';
$lang['license_subst:uwl'] = 'I don\'t know what the license is';

$lang['page_title'] = 'Video Editing Server';
$lang['site_title'] = 'Video Editing Server';

// Some simple action labels
$lang['go'] = 'Go';
$lang['edit'] = 'Edit';
$lang['save'] = 'Save';
$lang['cancel'] = 'Cancel';
$lang['search'] = 'Search';
$lang['delete'] = 'Delete';
$lang['restore'] = 'Restore';
$lang['download'] = 'Download';

// Menu items
$lang['menu_login'] = 'Login';
$lang['menu_logout'] = 'Logout';
$lang['menu_upload'] = 'Upload';
$lang['menu_main'] = 'Main Page';
$lang['menu_tools'] = 'Tools';
$lang['menu_recent'] = 'Changes';

// Main and extra pages
$lang['main_greeting'] = '<p>Under construction</p>';

// User area
$lang['user_register_button'] = 'Register';
$lang['user_name'] = 'User';
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
$lang['user_error_register'] = 'A user with this name already exists.';
$lang['user_view_heading'] = 'User: {name} ({role})';
$lang['user_view_footer'] = 'This user was last modified {updated_at} UTC.';
$lang['user_view_nocomment'] = 'No comment.';
$lang['user_list_heading'] = 'List Users';
$lang['user_files_caption'] = 'This user\'s files';

// Tools area
$lang['tools_user_mgmt'] = 'User Management';
$lang['tools_bureaucrats'] = 'List bureaucrats';
$lang['tools_administrators'] = 'List administrators';
$lang['tools_list_users'] = 'List users';
$lang['tools_list_guests'] = 'List guests';
$lang['tools_lookup_user'] = 'Lookup user: ';
$lang['tools_username_placeholder'] = 'Username';

// Files area
$lang['file_new_heading'] = 'Upload File';
$lang['file_new_message'] = 'Please enter some information about the file you are about to upload.';
$lang['file_edit_heading'] = 'Editing File: {title}';
$lang['file_view_heading'] = 'File: {title}';
$lang['file_view_footer'] = 'This file was last modified {updated_at} UTC.';
$lang['file_title'] = 'Title';
$lang['file_author'] = 'Author';
$lang['file_keywords'] = 'Keywords';
$lang['file_description'] = 'Description';
$lang['file_recording_date'] = 'Date';
$lang['file_language'] = 'Language';
$lang['file_license'] = 'License';
$lang['file_error_permission'] = 'You do not have permission to edit this file.';
$lang['file_error_heading'] = 'File Error';
$lang['file_error_update'] = 'Failed to update file.';
$lang['file_recent_heading'] = 'Latest Changes';
$lang['file_search_results_heading'] = 'Search Results: {query}';
$lang['file_updated_at'] = 'Modified';
$lang['file_revision'] = '#';
$lang['file_history_caption'] = 'Change History';
$lang['file_history_none'] = 'No Change History';
$lang['file_differences_heading'] = 'Change {revision} made on {updated_at} by {username}';
$lang['file_keywords_placeholder'] = 'Press Enter to separate keywords';
$lang['file_search_results_none'] = 'No results';
$lang['file_edit_message'] = '';
$lang['file_upload_button'] = 'Click to choose a file or drag &amp; drop a file onto the page.';
$lang['file_upload_success'] = 'File upload completed successfully!';
$lang['file_upload_resume'] = 'Incomplete: choose "{filename}" to resume.';
$lang['file_upload'] = 'Upload';
$lang['file_mime_type'] = 'MIME Type';
$lang['file_size'] = 'Size';
$lang['file_duration'] = 'Duration';
$lang['file_status'] = 'Status';

$lang['status_noupload'] = 'No upload, please click Edit to start.';
$lang['upload_partialupload'] = 'Partially uploaded, please click Edit to resume.';
$lang['status_uploaded'] = 'Uploaded';
$lang['status_converting'] = 'Converting Now';
$lang['status_converted'] = 'Transcoded';
$lang['status_validated'] = 'Validated';
$lang['status_error_validate'] = 'Invalid';
$lang['status_error_transcode'] = 'Transcode Error';

$lang['file_missing_caption'] = 'The following files are missing and must be uploaded to render this project.';
$lang['file_children_caption'] = 'Other files used by this project';
$lang['file_projects_caption'] = 'Projects that use this file';
$lang['file_upload_revision'] = 'Upload New Version';
$lang['change_comment'] = 'Comment';
$lang['file_download_project'] = 'Download Project XML';
$lang['file_properties'] = 'Properties';
$lang['file_properties_name'] = 'Name';
$lang['file_properties_value'] = 'Value';
$lang['add'] = 'Add';
$lang['remove'] = 'Remove';
$lang['move'] = 'Move';
