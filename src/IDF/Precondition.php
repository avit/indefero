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

class IDF_Precondition
{
    /**
     * Check if the user has a base authorization to access a given
     * tab. This used in the case of private project. You need to
     * further control with the accessSource, accessIssues,
     * etc. preconditions.
     *
     * @param Pluf_HTTP_Request
     * @return mixed
     */
    static public function baseAccess($request)
    {
        if (!$request->project->private) {
            return true;
        }
        if ($request->user->hasPerm('IDF.project-authorized-user', $request->project)) {
            return true;
        }
        return self::projectMemberOrOwner($request);
    }

    /**
     * Check if the user is project owner.
     *
     * @param Pluf_HTTP_Request
     * @return mixed
     */
    static public function projectOwner($request)
    {
        $res = Pluf_Precondition::loginRequired($request);
        if (true !== $res) {
            return $res;
        }
        if ($request->user->hasPerm('IDF.project-owner', $request->project)) {
            return true;
        }
        return new Pluf_HTTP_Response_Forbidden($request);
    }

    /**
     * Check if the user is project owner or member.
     *
     * @param Pluf_HTTP_Request
     * @return mixed
     */
    static public function projectMemberOrOwner($request)
    {
        $res = Pluf_Precondition::loginRequired($request);
        if (true !== $res) {
            return $res;
        }
        if ($request->user->hasPerm('IDF.project-owner', $request->project)
            or
            $request->user->hasPerm('IDF.project-member', $request->project)
            ) {
            return true;
        }
        return new Pluf_HTTP_Response_Forbidden($request);
    }

    /**
     * Check if the user can access a given element.
     *
     * The rights are:
     *  - 'all' (default)
     *  - 'none'
     *  - 'login'
     *  - 'members'
     *  - 'owners'
     *
     * The order of the rights is such that a 'owner' is also a
     * 'member' and of course a logged in person.
     *
     * @param Pluf_HTTP_Request
     * @param string Control key
     * @return mixed
     */
    static public function accessTabGeneric($request, $key)
    {
        switch ($request->conf->getVal($key, 'all')) {
        case 'none':
            return new Pluf_HTTP_Response_Forbidden($request);
        case 'login':
            return Pluf_Precondition::loginRequired($request);
        case 'members':
            return self::projectMemberOrOwner($request);
        case 'owners':
            return self::projectOwner($request);
        case 'all':
        default:
            return true;
        }
    }

    static public function accessSource($request)
    {
        $res = self::baseAccess($request);
        if (true !== $res) {
            return $res;
        }
        return self::accessTabGeneric($request, 'source_access_rights');
    }

    static public function accessIssues($request)
    {
        $res = self::baseAccess($request);
        if (true !== $res) {
            return $res;
        }
        return self::accessTabGeneric($request, 'issues_access_rights');
    }

    static public function accessDownloads($request)
    {
        $res = self::baseAccess($request);
        if (true !== $res) {
            return $res;
        }
        return self::accessTabGeneric($request, 'downloads_access_rights');
    }

    static public function accessWiki($request)
    {
        $res = self::baseAccess($request);
        if (true !== $res) {
            return $res;
        }
        return self::accessTabGeneric($request, 'wiki_access_rights');
    }

    /**
     * Based on the request, it is automatically setting the user.
     *
     * API calls are not translated.
     */
    static public function apiSetUser($request)
    {
        // REQUEST is used to be used both for POST and GET requests.
        if (!isset($request->REQUEST['_hash'])
            or !isset($request->REQUEST['_login'])
            or !isset($request->REQUEST['_salt'])) {
            // equivalent to anonymous access.
            return true;
        }
        $db =& Pluf::db();
        $true = Pluf_DB_BooleanToDb(true, $db);
        $sql = new Pluf_SQL('login=%s AND active='.$true,
                            $request->REQUEST['_login']);
        $users = Pluf::factory('Pluf_User')->getList(array('filter'=>$sql->gen()));
        if ($users->count() != 1) {
            // Should return a special authentication error like user
            // not found.
            return true;
        }
        $hash = sha1($request->REQUEST['_salt'].sha1($users[0]->password));
        if ($hash != $request->REQUEST['_hash']) {
            return true; // Again need authentication error
        }
        $request->user = $users[0];
        return true;
    }
}