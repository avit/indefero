<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008 Loic d'Anterroches and contributors.
#
# Plume CMS is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# Plume CMS is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

$m = array();
$m['IDF_Tag'] = array('relate_to' => array('IDF_Project'));
$m['IDF_Issue'] = array('relate_to' => array('IDF_Project', 'Pluf_User', 'IDF_Tag'),
                        'relate_to_many' => array('IDF_Tag', 'Pluf_User'));
$m['IDF_IssueComment'] = array('relate_to' => array('IDF_Issue', 'Pluf_User'));

return $m;
