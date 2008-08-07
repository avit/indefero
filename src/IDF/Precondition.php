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
        return self::accessTabGeneric($request, 'source_access_rights');
    }

    static public function accessIssues($request)
    {
        return self::accessTabGeneric($request, 'issues_access_rights');
    }

    static public function accessDownloads($request)
    {
        return self::accessTabGeneric($request, 'downloads_access_rights');
    }
}