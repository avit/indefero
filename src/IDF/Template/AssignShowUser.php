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

Pluf::loadFunction('Pluf_HTTP_URL_urlForView');

/**
 * Show the name of a user in a template.
 *
 * It will automatically make the link to the profile if needed. In
 * the future it will allow us to add little badges on the side of the
 * user based on karma or whatever.
 *
 * This will also provide a consistent display of a user name in the
 * application.
 */
class IDF_Template_AssignShowUser extends Pluf_Template_Tag
{
    /**
     * We need the user object and the request.
     *
     * If the user object is null (for example a non associated
     * commit), we can use the $text value for an alternative display.
     *
     * @param string Which variable to assign
     * @param Pluf_User
     * @param Pluf_HTTP_Request
     * @param string Alternate text ('')
     */
    function start($var, $user, $request, $text='')
    {
        $t = new IDF_Template_ShowUser($this->context);
        $this->context->set($var, 
                            Pluf_Template::markSafe($t->start($user, $request, $text, false)));
    }
}
