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
 * Project middleware.
 *
 * It must be after the session middleware.
 */
class IDF_Middleware
{
    /**
     * Process the request.
     *
     * When processing the request, check if matching a project. If
     * so, directly set $request->project to the project.
     *
     * The url to match a project is in the format /p/(\w+)/whatever
     * or /api/p/(\w+)/whatever. This means that it will not try to
     * match on /login/ or /logout/.
     *
     * @param Pluf_HTTP_Request The request
     * @return bool false or redirect.
     */
    function process_request(&$request)
    {
        $match = array();
        if (preg_match('#^/(?:api/p|p)/([\-\w]+)/#', $request->query, $match)) {
            try {
                $request->project = IDF_Project::getOr404($match[1]);
            } catch (Pluf_HTTP_Error404 $e) {
                return new Pluf_HTTP_Response_NotFound($request);
            }
            $request->conf = new IDF_Conf();
            $request->conf->setProject($request->project);
            self::setRights($request);
        }
        return false;
    }

    public static function setRights(&$request)
    {
        $ak = array('downloads_access_rights' => 'hasDownloadsAccess',
                    'wiki_access_rights' => 'hasWikiAccess',
                    'review_access_rights' => 'hasReviewAccess',
                    'source_access_rights' => 'hasSourceAccess',
                    'issues_access_rights' => 'hasIssuesAccess');
        $request->rights = array();
        foreach ($ak as $key=>$val) {
            $request->rights[$val] = (true === IDF_Precondition::accessTabGeneric($request, $key));
        }
    }

    /**
     * Update the template tags and modifiers to not have them in the config.
     *
     * This is here at the moment because we do not want to put that
     * in a IDF_Template class just for one method.
     *
     */
    public static function updateTemplateTagsModifiers($sender, &$params)
    {
        $params['tags'] = array_merge($params['tags'],
                                      array(
                              'hotkey' => 'IDF_Template_HotKey',
                              'issuetext' => 'IDF_Template_IssueComment',
                              'timeline' => 'IDF_Template_TimelineFragment',
                              'markdown' => 'IDF_Template_Markdown',
                                            ));
        $params['modifiers'] = array_merge($params['modifiers'],
                                           array(
                                   'size' => 'IDF_Views_Source_PrettySize',
                                   'ssize' => 'IDF_Views_Source_PrettySizeSimple',
                                                 ));
    }
}


function IDF_Middleware_ContextPreProcessor($request)
{
    $c = array();
    $c['request'] = $request;
    $c['isAdmin'] = ($request->user->administrator or $request->user->staff);
    if (isset($request->project)) {
        $c['project'] = $request->project;
        $c['isOwner'] = $request->user->hasPerm('IDF.project-owner', 
                                                $request->project);
        $c['isMember'] = $request->user->hasPerm('IDF.project-member', 
                                                 $request->project);
        $c = array_merge($c, $request->rights);
    }
    return $c;
}

