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

$config['debug'] = false;
$config['videos_dir'] = '/media/videos/';

$config['oauth_base_url']= 'https://commons.wikimedia.org/wiki/Special:OAuth';
$config['oauth_consumer_token'] = '70dcdf4058772ddc6e89a90170e4febe';
$config['oauth_private_key'] = 'other/mediawiki-oauth-key.pem';
$config['oauth_jwt_issuer'] = 'http://commons.wikimedia.org';
$config['cookie_expire_seconds'] = 2592000; // 30 days
