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
 * Manage differents SCM systems.
 *
 * This is the base class with the different required methods to be
 * implemented by the SCMs. Each SCM backend need to extend this
 * class. We are not using an interface because this is not really
 * needed.
 *
 * The philosophy behind the interface is not to provide a wrapper
 * around the different SCMs but to provide methods to retrieve in the
 * most efficient way the informations to be displayed/needed in the
 * web interface. This means that each SCM can use the best options,
 * including caching to retrieve the informations.
 *
 * Note on caching: You must not cache ephemeral information like the
 * changelog, but you can cache the commit info (except with
 * subversion where you can change commit info...).
 *
 * All the output of the methods must be serializable. This means that
 * if you are parsing XML you need to correctly cast the results as
 * string when needed.
 */
class IDF_Scm
{
    /**
     * String template for consistent error messages.
     */
    public $error_tpl = 'Error command "%s" returns code %d and output: %s';

    /**
     * Path to the repository.
     */
    public $repo = '';

    /**
     * Corresponding project object.
     */
    public $project = null;

    /**
     * Cache storage. 
     *
     * It must only be used to store data for the lifetime of the
     * object. For example if you need to get the list of branches in
     * several functions, better to try to get from the cache first.
     */
    protected $cache = array();

    /**
     * Default constructor.
     */
    public function __construct($repo, $project=null)
    {
        $this->repo = $repo;
        $this->project = $project;
    }

    /**
     * Returns an instance of the correct scm backend object.
     *
     * @param IDF_Project
     * @return Object
     */
    public static function get($project=null)
    {
        // Get scm type from project conf ; defaults to git
        // We will need to cache the factory
        $scm = $project->getConf()->getVal('scm', 'git');
        $scms = Pluf::f('allowed_scm');
        return call_user_func(array($scms[$scm], 'factory'), $project);
    }

    /**
     * Check if the backend is available for display.
     *
     * @return bool Available
     */
    public function isAvailable()
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Returns the list of branches.
     *
     * @return array For example array('trunk', '1.0branch')
     */
    public function getBranches()
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Returns the list of tags.
     *
     * @return array For example array('v0.9', 'v1.0')
     */
    public function getTags()
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Returns the main branch.
     *
     * The main branch is the one displayed by default. For example
     * master, trunk or tip.
     *
     * @return string
     */
    public function getMainBranch()
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Returns the list of files in a given folder.
     *
     * The list is an array of standard class objects with attributes
     * for each file/directory/external element.
     *
     * This is the most important method of the SCM backend as this is
     * the one conveying the speed feeling of the application. All the
     * dirty optimization tricks are allowed there.
     *
     * @param string Revision or commit
     * @param string Folder ('/')
     * @param string Branch (null)
     * @return array 
     */
    public function getTree($rev, $folder='/', $branch=null)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Get commit details.
     *
     * @param string Commit or revision number
     * @param bool Get commit diff (false)
     * @return stdClass
     */
    public function getCommit($commit, $getdiff=false)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Get latest changes.
     *
     * It default to the main branch. If possible you should code in a
     * way to avoid repetitive calls to getCommit. Try to be
     * efficient.
     *
     * @param string Branch (null)
     * @param int Number of changes (25)
     * @return array List of commits
     */
    public function getChangeLog($branch=null, $n=10)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Given the string describing the author from the log find the
     * author in the database.
     *
     * If the input is an array, it will return an array of results.
     *
     * @param mixed string/array Author
     * @return mixed Pluf_User or null or array
     */
    public function findAuthor($author)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Given a revision and a file path, retrieve the file content.
     *
     * The third parameter is to only request the command that is used
     * to get the file content. This is used when downloading a file
     * at a given revision as it can be passed to a
     * Pluf_HTTP_Response_CommandPassThru reponse. This allows to
     * stream a large response without buffering it in memory.
     *
     * The file definition can be a hash or a path depending on the
     * SCM.
     *
     * @param string File definition
     * @param string Revision ('')
     * @param bool Returns command only (false)
     * @return string File content
     */
    public function getFile($def, $rev='', $cmd_only=false)
    {
        throw new Pluf_Exception_NotImplemented();
    }


    /**
     * Equivalent to exec but with caching.
     *
     * @param string Command
     * @param &array Output
     * @param &int Return value
     * @return string Last line of the output
     */
    public static function exec($command, &$output=array(), &$return=0)
    {
        $command = Pluf::f('idf_exec_cmd_prefix', '').$command;
        $key = md5($command);
        $cache = Pluf_Cache::factory();
        if (null === ($res=$cache->get($key))) {
            $ll = exec($command, $output, $return);
            if ($return != 0 and Pluf::f('debug_scm', false)) {
                throw new IDF_Scm_Exception(sprintf('Error when running command: "%s", return code: %d', $command, $return));
            }
            $cache->set($key, array($ll, $return, $output));
        } else {
            list($ll, $return, $output) = $res;
        }
        return $ll;
    }

    /**
     * Equivalent to shell_exec but with caching.
     *
     * @param string Command
     * @return string Output of the command
     */
    public static function shell_exec($command)
    {
        $command = Pluf::f('idf_exec_cmd_prefix', '').$command;
        $key = md5($command);
        $cache = Pluf_Cache::factory();
        if (null === ($res=$cache->get($key))) {
            $res = shell_exec($command);
            $cache->set($key, $res);
        } 
        return $res;
    }

    /**
     * Sync the changes in the repository with the timeline.
     *
     */
    public static function syncTimeline($project)
    {
        $cache = Pluf_Cache::factory();
        $key = 'IDF_Scm:'.$project->shortname.':lastsync'; 
        if (null === ($res=$cache->get($key))) {
            $scm = IDF_Scm::get($project);
            foreach ($scm->getBranches() as $branche) {
                foreach ($scm->getChangeLog($branche, 25) as $change) {
                    IDF_Commit::getOrAdd($change, $project);
                }
            }
            $cache->set($key, true, (int)(Pluf::f('cache_timeout', 300)/2));
        }
    }
}

