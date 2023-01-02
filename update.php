<?PHP
/*
 * Clone Script for git repo mirroring/synchronisation
 *
 * This script can be used to mirror a git repo, with selected branches and tags, to another
 * repository. It was created because of gitlab's stupid idea to prevent repo mirroring on the
 * community edition, requiring you to pay a ridiculous subscription fee. And I would bet even if
 * you did, it wouldn't work anywhere near as well as this script at minimising what you push to
 * your gitlab mirror repository. The idea for this script is that you run it with a cron job as
 * needed, the user that it runs under must be able to access the folder and external repo and be
 * able to push to the internal repo, and be able to run git commands.
 *
 * Copyright (c) thedjnK 2023, released under Apache 2.0 license with no warranty or guarantee
 * implied. Use or adapt at your own risk.
 *
 * SPDX-License-Identifier: Apache-2.0
 */

/* Script configuration */
$ExternalRepo = 'https://github.com/zephyrproject-rtos/zephyr.git'; /* External clone URL - best to use non-authenticated unless running user has ssh identity added */
$InternalRepo = ''; /* Internal repo to push to, running user must have push rights e.g. ssh identity */
$CloneFolder = '/tmp/zephyr_repo'; /* Temporary folder to clone to, not including repo name (note: will only work if the above-level folder exists) */
$RepoName = 'zephyr'; /* Repo name, must match the folder name that the repository gets cloned into */
$KeepBranches = array('main', 'v3.2-branch', 'v2.7-branch'); /* List of branches to keep */
$KeepTags = array('v2.7.0', 'zephyr-v2.7.0', 'v2.7.1', 'zephyr-v2.7.1', 'v2.7.2', 'zephyr-v2.7.2', 'v2.7.3', 'zephyr-v2.7.3', 'v2.7.4', 'zephyr-v2.7.4', 'v3.2.0', 'zephyr-v3.2.0'); /* List of tags to keep */

/* No user configuration below */
$CmdDescriptors = array(
	array('pipe', 'r'), /* stdin */
	array('pipe', 'w'), /* stdout */
	array('pipe', 'r'), /* stderr */
);

/* Make directory if it does not exist */
if (!is_dir($CloneFolder))
{
	mkdir($CloneFolder);
}

/* Check if it has been cloned already */
$GitStatus = proc_open('git status', $CmdDescriptors, $Pipes, $CloneFolder.'/'.$RepoName);

if (!is_resource($GitStatus))
{
	die('Git status resource failed.');
}

/* Discard stdout and stderr and close pipes */
fclose($Pipes[0]);
stream_get_contents($Pipes[1]);
fclose($Pipes[1]);
fclose($Pipes[2]);

$ExitCode = proc_close($GitStatus);

if ($ExitCode == 128)
{
	/* Clone repository */
	$GitClone = proc_open('git clone '.$ExternalRepo, $CmdDescriptors, $Pipes, $CloneFolder);

	if (!is_resource($GitClone))
	{
		die('Git clone resource failed.');
	}

	/* Discard stdout and stderr and close pipes */
	fclose($Pipes[0]);
	stream_get_contents($Pipes[1]);
	fclose($Pipes[1]);
	fclose($Pipes[2]);

	$ExitCode = proc_close($GitClone);

	if ($ExitCode != 0)
	{
		die('Git clone failed: '.$ExitCode);
	}
}
else if ($ExitCode == 0)
{
	/* Pull down changes from repository */
	$GitPull = proc_open('git pull origin', $CmdDescriptors, $Pipes, $CloneFolder.'/'.$RepoName);

	if (!is_resource($GitPull))
	{
		die('Git pull resource failed.');
	}

	/* Discard stdout and stderr and close pipes */
	fclose($Pipes[0]);
	stream_get_contents($Pipes[1]);
	fclose($Pipes[1]);
	fclose($Pipes[2]);

	$ExitCode = proc_close($GitPull);

	if ($ExitCode != 0)
	{
		die('Git pull failed: '.$ExitCode);
	}
}
else
{
	/* Unknown error */
	die('Unknown git status error: '.$ExitCode);
}

/* Check out all branches so we have them and pull in changes */
$i = 0;
$l = count($KeepBranches);

while ($i < $l)
{
	/* Checkout each branch */
	$GitCheckout = proc_open('git checkout '.$KeepBranches[$i], $CmdDescriptors, $Pipes, $CloneFolder.'/'.$RepoName);

	if (!is_resource($GitCheckout))
	{
		die('Git checkout resource failed.');
	}

	/* Discard stdout and stderr and close pipes */
	fclose($Pipes[0]);
	stream_get_contents($Pipes[1]);
	fclose($Pipes[1]);
	fclose($Pipes[2]);

	$ExitCode = proc_close($GitCheckout);

	if ($ExitCode != 0)
	{
		die('Git checkout failed: '.$ExitCode);
	}

	/* Pull in changes */
	$GitPull = proc_open('git pull origin', $CmdDescriptors, $Pipes, $CloneFolder.'/'.$RepoName);

	if (!is_resource($GitPull))
	{
		die('Git pull resource failed.');
	}

	/* Discard stdout and stderr and close pipes */
	fclose($Pipes[0]);
	stream_get_contents($Pipes[1]);
	fclose($Pipes[1]);
	fclose($Pipes[2]);

	$ExitCode = proc_close($GitPull);

	if ($ExitCode != 0)
	{
		die('Git pull failed: '.$ExitCode);
	}

	++$i;
}

/* Get list of tags and remove any we do not want */
$GitTagList = proc_open('git tag -l', $CmdDescriptors, $Pipes, $CloneFolder.'/'.$RepoName);

if (!is_resource($GitTagList))
{
	die('Git tag list resource failed.');
}

/* Read stdout, discard stderr and close pipes */
fclose($Pipes[0]);
$Tags = explode("\n", stream_get_contents($Pipes[1]));
fclose($Pipes[1]);
fclose($Pipes[2]);

$ExitCode = proc_close($GitTagList);

if ($ExitCode != 0)
{
	die('Git tag list failed: '.$ExitCode);
}

unset($Tags[(count($Tags) - 1)]);

$i = 0;
$l = count($Tags);

while ($i < $l)
{
	if (array_search($Tags[$i], $KeepTags, true) === false)
	{
		/* Tag to be removed */
		$GitTagDelete = proc_open('git tag -d '.$Tags[$i], $CmdDescriptors, $Pipes, $CloneFolder.'/'.$RepoName);

		if (!is_resource($GitTagDelete))
		{
			die('Git tag delete resource failed.');
		}

		/* Discard stdout, stderr and close pipes */
		fclose($Pipes[0]);
		stream_get_contents($Pipes[1]);
		fclose($Pipes[1]);
		fclose($Pipes[2]);

		proc_close($GitTagDelete);
	}

	++$i;
}

/* Compress repository */
$GitCompress = proc_open('git gc --auto -q --prune --cruft --aggressive', $CmdDescriptors, $Pipes, $CloneFolder.'/'.$RepoName);

if (!is_resource($GitCompress))
{
	die('Git compress resource failed.');
}

/* Discard stdout, stderr and close pipes */
fclose($Pipes[0]);
stream_get_contents($Pipes[1]);
fclose($Pipes[1]);
fclose($Pipes[2]);

$ExitCode = proc_close($GitCompress);

if ($ExitCode != 0)
{
	die('Git compress failed: '.$ExitCode);
}

/* Add our internal remote */
$GitRemoteAdd = proc_open('git remote add internal '.$InternalRepo, $CmdDescriptors, $Pipes, $CloneFolder.'/'.$RepoName);

if (!is_resource($GitRemoteAdd))
{
	die('Git remote add resource failed.');
}

/* Discard stdout, stderr and close pipes */
fclose($Pipes[0]);
stream_get_contents($Pipes[1]);
fclose($Pipes[1]);
fclose($Pipes[2]);

$ExitCode = proc_close($GitRemoteAdd);

if ($ExitCode != 0 && $ExitCode != 3)
{
	die('Git remote add failed: '.$ExitCode);
}

/* Push changes for all branches */
$i = 0;
$l = count($KeepBranches);

while ($i < $l)
{
	/* Checkout each branch */
	$GitCheckout = proc_open('git checkout '.$KeepBranches[$i], $CmdDescriptors, $Pipes, $CloneFolder.'/'.$RepoName);

	if (!is_resource($GitCheckout))
	{
		die('Git checkout resource failed.');
	}

	/* Discard stdout, stderr and close pipes */
	fclose($Pipes[0]);
	stream_get_contents($Pipes[1]);
	fclose($Pipes[1]);
	fclose($Pipes[2]);

	$ExitCode = proc_close($GitCheckout);

	if ($ExitCode != 0)
	{
		die('Git checkout failed: '.$ExitCode);
	}

	/* Push changes */
	$GitPush = proc_open('git push internal', $CmdDescriptors, $Pipes, $CloneFolder.'/'.$RepoName);

	if (!is_resource($GitPush))
	{
		die('Git push resource failed.');
	}

	/* Discard stdout, stderr and close pipes */
	fclose($Pipes[0]);
	stream_get_contents($Pipes[1]);
	fclose($Pipes[1]);
	fclose($Pipes[2]);

	$ExitCode = proc_close($GitPush);

	if ($ExitCode != 0)
	{
		die('Git push failed: '.$ExitCode);
	}

	++$i;
}
?>
