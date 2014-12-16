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
$config['publish_endpoint']= 'https://commons.wikimedia.org/w/api.php';

// http://php.local/index.php?oath-callback
$config['oauth_consumer_token'] = '70dcdf4058772ddc6e89a90170e4febe';
// https://wikimedia-video-editing-server-ddennedy.c9.io/index.php/oauth-callback
// $config['oauth_consumer_token'] = '7d5a9d401d351da32c8ef0e6df71ae02';
// secret = 95cc2d20d1682bc85fb6cc4d984bed3b809a72fb

$config['oauth_private_key'] = 'other/mediawiki-oauth-key.pem';
$config['oauth_jwt_issuer'] = 'http://commons.wikimedia.org';
$config['cookie_expire_seconds'] = 2592000; // 30 days
$config['recent_limit'] = 100;
$config['search_limit'] = 25;
$config['upload_path'] = '/var/www/uploads/';
$config['upload_vdir'] = 'uploads/';
$config['transcode_path'] = '/var/www/transcodes/';
$config['transcode_vdir'] = 'transcodes/';

$config['beanstalkd_host'] = 'mysql';
$config['beanstalkd_tube_validate'] = 'videoeditserver-validate';
// Lump heavy transcode and MLT XML render jobs into the same tube to be
// processed by the same set of workers.
$config['beanstalkd_tube_transcode'] = 'videoeditserver-encode';
$config['beanstalkd_tube_render'] = 'videoeditserver-encode';

$config['transcode_audio_extension'] = 'ogg';
$config['transcode_audio_options'] = '-map 0:a -codec libvorbis -qscale 5 -y';
$config['transcode_video_extension'] = 'webm';
$config['transcode_video_options'] = '-map 0 -map -0:d -map -0:s -map -0:t -vf yadif=mode=send_frame:deint=interlaced -codec:a libvorbis -qscale:a 5 -codec:v libvpx -g 100 -quality good -speed 0 -vprofile 0 -slices 4 -threads 2 -b:v 10M -crf 10 -arnr_max_frames 7 -arnr_strength 5 -arnr_type 3 -y';
$config['render_extension'] = 'webm';
$config['render_options'] = 'progressive=1 acodec=libvorbis aqscale=5 vcodec=libvpx g=100 quality=good speed=0 vprofile=0 slices=4 threads=1 vb=10M crf=10 arnr_max_frames=7 arnr_strength=5 arnr_type=3';
$config['http_client_user_agent'] = 'VideoEditingServer/1.0 (https://github.com/ddennedy/wikimedia-video-editing-server; dan@dennedy.org)';
