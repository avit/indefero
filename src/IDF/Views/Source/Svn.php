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
 * View git repository.
 */
class IDF_Views_Source_Svn
{
    /**
     * Display tree of a specific SVN revision
     *
     */
    public function treeRev($request, $match)
    {
        $prj = $request->project;
        if ($request->conf->getVal('scm', 'git') != 'svn') {
            // Redirect to tree base if not svn
            $url =  Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                  array($prj->shortname, $prj->getScmRoot()));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        // Get revision value
        if (!isset($request->REQUEST['rev']) 
            or trim($request->REQUEST['rev']) == '') {
            $scmRoot = $prj->getScmRoot();
        } else {
            $scmRoot = $request->REQUEST['rev'];
        }
        // Get source if not /
        if (isset($request->REQUEST['sourcefile']) 
            and trim($request->REQUEST['sourcefile']) != '') {
            $scmRoot .= '/'.$request->REQUEST['sourcefile'];
        }
        $url =  Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                         array($prj->shortname, $scmRoot));
        return new Pluf_HTTP_Response_Redirect($url);
    }

    /**
     * Display SVN changelog from specific revision
     *
     */
    public function changelogRev($request, $match)
    {
        $prj = $request->project;
        if ($request->conf->getVal('scm', 'git') != 'svn') {
            // Redirect to tree base if not svn
            $scmRoot = $prj->getScmRoot();
        } else {
            // Get revision value if svn
            if (!isset($request->REQUEST['rev']) 
                or trim($request->REQUEST['rev']) == '') {
                $scmRoot = $prj->getScmRoot();
            } else {
                $scmRoot = $request->REQUEST['rev'];
            }
        }
        $url =  Pluf_HTTP_URL_urlForView('IDF_Views_Source::changeLog',
                                         array($prj->shortname, $scmRoot));
        return new Pluf_HTTP_Response_Redirect($url);
    }
}