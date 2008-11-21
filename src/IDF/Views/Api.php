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
 * API views.
 *
 * These are just small wrappers around the "normal" views. The normal
 * views are called with a third parameters $api set to true to return
 * JSON instead of HTML.
 *
 * A special precondition is used to set the $request->user from the
 * _login, _hash and _salt parameters. 
 */
class IDF_Views_Api
{
    /**
     * View list of issues for a given project.
     */
    public $issuesIndex_precond = array('IDF_Precondition::apiSetUser',
                                        'IDF_Precondition::accessIssues');
    public function issuesIndex($request, $match)
    {
        $views = new IDF_Views_Issue();
        $p = $views->index($request, $match, true);
        $out = array(
                     'project' => $request->project->shortname,
                     'open' => $p['open'],
                     'closed' => $p['closed'],
                     'issues' => $p['issues']->render_array(),
                     );
        return new Pluf_HTTP_Response_Json($out);
    }

    /**
     * Create a new issue.
     */
    public $issueCreate_precond = array('IDF_Precondition::apiSetUser',
                                        'IDF_Precondition::accessIssues');
    public function issueCreate($request, $match)
    {
        $views = new IDF_Views_Issue();
        $p = $views->create($request, $match, true);
        $out = array();
        if ($request->method == 'GET') {
            // We give the details of the form
            $out['doc'] = 'A POST request against this url will allow you to create a new issue.';
            if ($request->user->hasPerm('IDF.project-owner', $request->project)
                or $request->user->hasPerm('IDF.project-member', $request->project)) {
                $out['status'] = array();
                foreach (self::getTags($request->project) as $tag) {
                    $out['status'][] = $tag->name;
                }
            }

        } else {
            // We need to give back the results of the creation
            if (is_object($p) and 'IDF_Issue' == get_class($p)) {
                $out['mess'] = 'success';
                $out['issue'] = $p->id;
            } else {
                $out['mess'] = 'error';
                $out['errors'] = $p['form']->errors;
            }
        }
        return new Pluf_HTTP_Response_Json($out);
    }

    /**
     * Get the list of tags to give them to the end users when doing a
     * GET request against a form. That way it is possible for them to
     * know which tags/labels are available.
     *
     * @param IDF_Project Current project
     * @param string Which tags to get ('issue-open')
     * @return ArrayObject Tags
     */

    public static function getTags($project, $what='issue-open')
    {
        switch ($what) {
        case 'issue-open':
            $key = 'labels_issue_open';
            $default = IDF_Form_IssueTrackingConf::init_open;
            return $project->getTagsFromConfig($key, $default);
        case 'issue-closed':
            return $project->getTagIdsByStatus('closed');
        }
        return array();
    }
}