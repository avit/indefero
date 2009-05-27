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
 * Subversion backend.
 * When a branch is not a branch.
 * 
 * Contrary to most other SCMs, Subversion is using folders to manage
 * the branches and so what is either the commit or the branch in
 * other SCMs is the revision number with Subversion. So, do not be
 * surprised if you have the feeling that the methods are not really
 * returning what could be expected from their names.
 */
class IDF_Scm_Svn extends IDF_Scm
{
    public $repo = '';
    public $username = '';
    public $password = '';
    private $assoc = array('dir' => 'tree',
                           'file' => 'blob');

    private $commit=array();

    public function __construct($repo, $project=null, $username='', $password='')
    {
        $this->repo = $repo;
        $this->project = $project;
        $this->username = $username;
        $this->password = $password;
        $this->cache['commitmess'] = array();
    }

    public function isAvailable()
    {
        return true;
    }

    /**
     * Given the string describing the author from the log find the
     * author in the database.
     *
     * @param string Author
     * @return mixed Pluf_User or null
     */
    public function findAuthor($author)
    {
        $sql = new Pluf_SQL('login=%s', array(trim($author)));
        $users = Pluf::factory('Pluf_User')->getList(array('filter'=>$sql->gen()));
        return ($users->count() > 0) ? $users[0] : null;
    }

    /**
     * Returns the URL of the subversion repository.
     *
     * @param IDF_Project
     * @return string URL
     */
    public static function getAnonymousAccessUrl($project)
    {
        $conf = $project->getConf();
        if (false !== ($url=$conf->getVal('svn_remote_url', false)) 
            && !empty($url)) {
            // Remote repository
            return $url;
        }
        return sprintf(Pluf::f('svn_remote_url'), $project->shortname);
    }

    /**
     * Returns the URL of the subversion repository.
     *
     * @param IDF_Project
     * @return string URL
     */
    public static function getAuthAccessUrl($project, $user)
    {
        $conf = $project->getConf();
        if (false !== ($url=$conf->getVal('svn_remote_url', false)) 
            && !empty($url)) {
            // Remote repository
            return $url;
        }
        return sprintf(Pluf::f('svn_remote_url'), $project->shortname);
    }

    /**
     * Returns this object correctly initialized for the project.
     *
     * @param IDF_Project
     * @return IDF_Scm_Svn
     */
    public static function factory($project)
    {
        $conf = $project->getConf();
        // Find the repository
        if (false !== ($rep=$conf->getVal('svn_remote_url', false)) 
            && !empty($rep)) {
            // Remote repository
            return new IDF_Scm_Svn($rep, $project,
                                   $conf->getVal('svn_username'),
                                   $conf->getVal('svn_password'));
        } else {
            $rep = sprintf(Pluf::f('svn_repositories'), $project->shortname);
            return new IDF_Scm_Svn($rep, $project);
        }
    }

    /**
     * Subversion revisions are either a number or 'HEAD'.
     */
    public function isValidRevision($rev)
    {
        if ($rev == 'HEAD') {
            return true;
        }
        $cmd = sprintf(Pluf::f('svn_path', 'svn').' info --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo),
                       escapeshellarg($rev));
        exec($cmd, $out, $ret);
        return (0 == $ret);
    }


    /**
     * Test a given object hash.
     *
     * @param string Object hash.
     * @return mixed false if not valid or 'blob', 'tree', 'commit'
     */
    public function testHash($rev, $path='')
    {
        // OK if HEAD on /
        if ($rev === 'HEAD' && $path === '') {
            return 'commit';
        }

        // Else, test the path on revision
        $cmd = sprintf(Pluf::f('svn_path', 'svn').' info --xml --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo.'/'.$path),
                       escapeshellarg($rev));
        $xmlInfo = shell_exec($cmd);

        // If exception is thrown, return false
        try {
            $xml = simplexml_load_string($xmlInfo);
        }
        catch (Exception $e) {
            return false;
        }

        // If the entry node does exists, params are wrong
        if (!isset($xml->entry)) {
            return false;
        }

        // Else, enjoy it :)
        return 'commit';
    }

    public function getTree($rev='HEAD', $folder='')
    {
        $cmd = sprintf(Pluf::f('svn_path', 'svn').' ls --xml --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo.'/'.$folder),
                       escapeshellarg($rev));
        $xml = simplexml_load_string(shell_exec($cmd));
        $res = array();
        $folder = (strlen($folder)) ? $folder.'/' : '';
        foreach ($xml->list->entry as $entry) {
            $file = array();
            $file['type'] = $this->assoc[(string) $entry['kind']];
            $file['file'] = (string) $entry->name;
            $file['fullpath'] = $folder.((string) $entry->name);
            $file['date'] = gmdate('Y-m-d H:i:s',
                                   strtotime((string) $entry->commit->date));
            $file['rev'] = (string) $entry->commit['revision'];
            $file['log'] = $this->getCommitMessage($file['rev']);
            // Get the size if the type is blob
            if ($file['type'] == 'blob') {
                $file['size'] = (string) $entry->size;
            }
            $file['author'] = (string) $entry->commit->author;
            $file['perm'] = '';
            $res[] = (object) $file;
        }
        return $res;
    }


    /**
     * Get the commit message of a revision revision.
     *
     * @param string Commit ('HEAD')
     * @return String commit message
     */
    private function getCommitMessage($rev='HEAD')
    {
        if (isset($this->cache['commitmess'][$rev])) {
            return $this->cache['commitmess'][$rev];
        }
        $cmd = sprintf(Pluf::f('svn_path', 'svn').' log --xml --limit 1 --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo),
                       escapeshellarg($rev));
        $xml = simplexml_load_string(shell_exec($cmd));
        $this->cache['commitmess'][$rev] = (string) $xml->logentry->msg;
        return $this->cache['commitmess'][$rev];
    }

    /**
     * FIXME: Need to check the case of an inexisting file.
     */
    public function getPathInfo($totest, $rev='HEAD')
    {
        $cmd = sprintf(Pluf::f('svn_path', 'svn').' info --xml --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo.'/'.$totest),
                       escapeshellarg($rev));
        $xml = simplexml_load_string(shell_exec($cmd));
        $entry = $xml->entry;
        $file = array();
        $file['fullpath'] = $totest;
        $file['hash'] = (string) $entry->repository->uuid;
        $file['type'] = $this->assoc[(string) $entry['kind']];
        $file['file'] = $totest;
        $file['rev'] = $rev; 
        $file['author'] = (string) $entry->author;
        $file['date'] = gmdate('Y-m-d H:i:s', strtotime((string) $entry->commit->date));
        $file['size'] = (string) $entry->size;
        $file['log'] = '';
        return (object) $file;
    }

    public function getFile($def)
    {
        $cmd = sprintf(Pluf::f('svn_path', 'svn').' cat --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo.'/'.$def->fullpath),
                       escapeshellarg($def->rev));
        return shell_exec($cmd);
    }

    /**
     * Subversion branches are repository based. 
     *
     * One need to list the folder to know them.
     */
    public function getBranches()
    {
        if (isset($this->cache['branches'])) {
            return $this->cache['branches'];
        }
        $res = array();
        $cmd = sprintf(Pluf::f('svn_path', 'svn').' ls --username=%s --password=%s %s@HEAD',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo.'/branches'));
        exec($cmd, $out, $ret);
        if ($ret == 0) {
            foreach ($out as $entry) {
                if (substr(trim($entry), -1) == '/') {
                    $branch = substr(trim($entry), 0, -1);
                    $res[$branch] = 'branches/'.$branch;
                }
            }
        }
        ksort($res);
        $cmd = sprintf(Pluf::f('svn_path', 'svn').' info --username=%s --password=%s %s@HEAD',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo.'/trunk'));
        exec($cmd, $out, $ret);
        if ($ret == 0) {
            $res = array('trunk' => 'trunk') + $res;
        }
        $this->cache['branches'] = $res;
        return $res;
    }

    public function getMainBranch()
    {
        return 'HEAD';
    }

    public function inBranches($commit, $path)
    {
        foreach ($this->getBranches() as $branch => $bpath) {
            if ($bpath and 0 === strpos($path, $bpath)) {
                return array($branch);
            }
        }
        return array();
    }


    /**
     * Get commit details.
     *
     * @param string Commit ('HEAD')
     * @param bool Get commit diff (false)
     * @return array Changes
     */
    public function getCommit($rev='HEAD', $getdiff=false)
    {
        $res = array();
        $cmd = sprintf(Pluf::f('svn_path', 'svn').' log --xml --limit 1 -v --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo),
                       escapeshellarg($rev));
        $xmlRes = shell_exec($cmd);
        $xml = simplexml_load_string($xmlRes);
        $res['author'] = (string) $xml->logentry->author;
        $res['date'] = gmdate('Y-m-d H:i:s', strtotime((string) $xml->logentry->date));
        $res['title'] = (string) $xml->logentry->msg;
        $res['commit'] = (string) $xml->logentry['revision'];
        $res['changes'] = ($getdiff) ? $this->getDiff($rev) : '';
        $res['tree'] = '';
        return (object) $res;
    }

    /**
     * Check if a commit is big.
     *
     * @param string Commit ('HEAD')
     * @return bool The commit is big
     */
    public function isCommitLarge($commit='HEAD')
    {
        if (substr($this->repo, 0, 7) != 'file://') {
            return false;
        }
        // We have a locally hosted repository, we can query it with
        // svnlook
        $repo = substr($this->repo, 7);
        $cmd = sprintf(Pluf::f('svnlook_path', 'svnlook').' changed -r %s %s',
                       escapeshellarg($commit),
                       escapeshellarg($repo));
        $out = shell_exec($cmd);
        $lines = preg_split("/\015\012|\015|\012/", $out);
        return (count($lines) > 100);
    }

    private function getDiff($rev='HEAD')
    {
        $res = array();
        $cmd = sprintf(Pluf::f('svn_path', 'svn').' diff -c %s --username=%s --password=%s %s',
                       escapeshellarg($rev),
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo));
        return shell_exec($cmd);
    }


    /**
     * Get latest changes.
     *
     * @param string Revision or ('HEAD').
     * @param int Number of changes (10).
     *
     * @return array Changes.
     */
    public function getChangeLog($rev='HEAD', $n=10)
    {
        if ($rev != 'HEAD' and !preg_match('/^\d+$/', $rev)) {
            // we accept only revisions or HEAD
            $rev = 'HEAD';
        }
        $res = array();
        $cmd = sprintf(Pluf::f('svn_path', 'svn').' log --xml -v --limit %s --username=%s --password=%s %s@%s',
                       escapeshellarg($n),
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo),
                       escapeshellarg($rev));
        $xmlRes = shell_exec($cmd);
        $xml = simplexml_load_string($xmlRes);
        foreach ($xml->logentry as $entry) {
            $log = array();
            $log['author'] = (string) $entry->author;
            $log['date'] = gmdate('Y-m-d H:i:s', strtotime((string) $entry->date));
            $log['title'] = (string) $entry->msg;
            $log['commit'] = (string) $entry['revision'];
            $log['full_message'] = '';

            $res[] = (object) $log;
        }
        return $res;
    }


    /**
     * Get additionnals properties on path and revision
     *
     * @param string File
     * @param string Commit ('HEAD')
     * @return array
     */
    public function getProperties($rev, $path='')
    {
        $res = array();
        $cmd = sprintf(Pluf::f('svn_path', 'svn').' proplist --xml --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo.'/'.$path),
                       escapeshellarg($rev));
        $xmlProps = shell_exec($cmd);
        $props = simplexml_load_string($xmlProps);

        // No properties, returns an empty array
        if (!isset($props->target)) {
            return $res;
        }

        // Get the value of each property
        foreach ($props->target->property as $prop) {
            $key = (string) $prop['name'];
            $res[$key] = $this->getProperty($key, $rev, $path);
        }

        return $res;
    }


    /**
     * Get a specific additionnal property on path and revision
     *
     * @param string Property
     * @param string File
     * @param string Commit ('HEAD')
     * @return string the property value
     */
    private function getProperty($property, $rev, $path='')
    {
        $res = array();
        $cmd = sprintf(Pluf::f('svn_path', 'svn').' propget --xml %s --username=%s --password=%s %s@%s',
                       escapeshellarg($property),
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo.'/'.$path),
                       escapeshellarg($rev));
        $xmlProp = shell_exec($cmd);
        $prop = simplexml_load_string($xmlProp);

        return (string) $prop->target->property;
    }


    /**
     * Get the number of the last commit in the repository.
     *
     * @param string Commit ('HEAD').
     *
     * @return String last number commit
     */
    public function getLastCommit($rev='HEAD')
    {
        $xmlInfo = '';
        $cmd = sprintf(Pluf::f('svn_path', 'svn').' info --xml --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo),
                       escapeshellarg($rev));
        $xmlInfo = shell_exec($cmd);

        $xml = simplexml_load_string($xmlInfo);
        return (string) $xml->entry->commit['revision'];
    }
}

