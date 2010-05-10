<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# InDefero is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * This script process the queue of items.
 *
 * At the moment the queue is only used for the webhooks, but it would
 * be good in the future to use it for indexing and email
 * notifications.
 *
 */

require dirname(__FILE__).'/../src/IDF/conf/path.php';
require 'Pluf.php';
Pluf::start(dirname(__FILE__).'/../src/IDF/conf/idf.php');
Pluf_Dispatcher::loadControllers(Pluf::f('idf_views'));

#;*/ ::
$lock_file = Pluf::f('idf_queuecron_lock', 
                     Pluf::f('tmp_folder', '/tmp').'/queuecron.lock');
if (file_exists($lock_file)) {
    Pluf_Log::event(array('queuecron.php', 'skip'));
    return;
}
file_put_contents($lock_file, time(), LOCK_EX);

/**
 * [signal]
 *
 * queuecron.php::run
 *
 * [sender]
 *
 * queuecron.php
 *
 * [description]
 *
 * This signal allows an application to perform a set of tasks when
 * the queue cron job is run. This is done usually every 5 minutes.
 *
 * [parameters]
 *
 * array()
 *
 */
$params = array();
Pluf_Signal::send('queuecron.php::run', 'queuecron.php', $params);

unlink($lock_file);
