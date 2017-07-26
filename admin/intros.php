<?php

/*
UHQ-IceAuth :: XOOPS Module for IceCast Authentication
Copyright (C) 2008-2013 :: Ian A. Underwood :: xoops@underwood-hq.org

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

include_once __DIR__ . '/admin_header.php';

if (!isset($xoopsTpl)) {
	$xoopsTpl = new XoopsTpl();
}
$xoopsTpl->caching=0;

include XOOPS_ROOT_PATH . "/modules/uhq_iceauth/includes/sanity.php";
include XOOPS_ROOT_PATH . "/modules/uhq_iceauth/admin/functions.inc.php";

include XOOPS_ROOT_PATH . "/class/xoopsformloader.php";
include XOOPS_ROOT_PATH . "/class/uploader.php";

$myts = MyTextsanitizer::getInstance();

// Now the fun begins!

if ( isset($_REQUEST['op']) ) {
	$op = $_REQUEST['op'];
} else {
	$op = "none";
}

$sane_REQUEST = uhqiceauth_dosanity();

function uhqiceauth_introform($title,$formdata=array(),$op=null) {

	if ($formdata == null) {
		$formdata['codec'] = 'O';
	}

	$form = new XoopsThemeForm($title,'introform','intros.php', 'post', true);

	if ($op == "edit") {
		$form->addElement(new XoopsFormFile(_AM_UHQICEAUTH_INTROS_EDITFILE, 'introfile', 1048576));
	} else {
		$form->addElement(new XoopsFormFile(_AM_UHQICEAUTH_INTROS_FILE, 'introfile', 1048576), true);
	}
	$form->setExtra('enctype="multipart/form-data"');

	$form_c = new XoopsFormSelect(_AM_UHQICEAUTH_INTROS_CODEC, 'codec', $formdata['codec'],1);
	$form_c->addOption('A',_AM_UHQICEAUTH_AAC);
	$form_c->addOption('P',_AM_UHQICEAUTH_AACPLUS);
	$form_c->addOption('M',_AM_UHQICEAUTH_MP3);
	$form_c->addOption('O',_AM_UHQICEAUTH_OGG);

	$form->addElement($form_c, true);

	$form->addElement(new XoopsFormDhtmlTextArea(_AM_UHQICEAUTH_INTROS_DESC,'description', $formdata['description'],4,60) );

	if ($op == "edit") {
		$form->addElement(new XoopsFormHidden("op", "edit") );
		$form->addElement(new XoopsFormHidden("intronum",$formdata['intronum']) );
	} else {
		$form->addElement(new XoopsFormHidden("op", "insert") );
	}

	$form->addElement(new XoopsFormHidden("verify", "1") );

	$form->addElement(new XoopsFormButton("",'post',$title,'submit') );

	$form->display();

}

function uhqiceauth_introdelform($title,$formdata) {

	$form = new XoopsThemeForm ($title,'introdelform','intros.php','post');

	$form->addElement(new XoopsFormHidden("intronum", $formdata['intronum']) );
	$form->addElement(new XoopsFormHidden("op","delete") );
	$form->addElement(new XoopsFormHidden("verify","1") );
	$form->addElement(new XoopsFormButton($formdata['filename'],'post',_AM_UHQICEAUTH_FORM_DELBUTTON,'submit') );

	$form->display();

}

switch ($op) {
	case "insert" :
		if ( isset($_REQUEST['verify']) ) {
			// If the upload is good, save the file and DB info.
			$uploader = new XoopsMediaUploader(XOOPS_ROOT_PATH."/modules/uhq_iceauth/intros",$uhqiceauth_intro_mimes,1048576);
			if ($uploader->fetchMedia($_POST['xoops_upload_file'][0]) ) {
				$uploader->setPrefix('intro-');
				if ( $uploader->upload() ) {
					$filetarget = $uploader->getSavedFileName();

					$query = "INSERT INTO ".$xoopsDB->prefix('uhqiceauth_intros');
					$query .= " SET codec='".$sane_REQUEST['codec']."', ";
					$query .= " filename='".$filetarget."', ";
					$query .= " description='".$sane_REQUEST['description']."'";

					$result = $xoopsDB->queryF($query);
					if ($result == false) {
						unlink (XOOPS_ROOT_PATH."/modules/uhq_iceauth/intros/".$filetarget);
						redirect_header("intros.php",10,_AM_UHQICEAUTH_SQLERR.$query);
					} else {
						redirect_header("intros.php",10,_AM_UHQICEAUTH_INTROS_ULOK." (".$filetarget.")");
					}
				} else {
					redirect_header("intros.php",10,_AM_UHQICEAUTH_ERR_ULSAVE.$uploader->getErrors());
				}
			} else {
				redirect_header("intros.php",10,_AM_UHQICEAUTH_ERR_FETCH.$uploader->getErrors());
			}
		} else {
			// Display page w/ form.
			xoops_cp_header();
			$mainAdmin = new ModuleAdmin();
			echo $mainAdmin->addNavigation('intros.php');
			uhqiceauth_introform(_AM_UHQICEAUTH_INTROS_ADD);
			include_once __DIR__ . '/admin_footer.php';

		}
		break;
	case "delete":
		if ( isset($sane_REQUEST['intronum'] ) ) {
			// Load Record
			$query = "SELECT * FROM ".$xoopsDB->prefix('uhqiceauth_intros');
			$query .= " WHERE intronum = '".$sane_REQUEST['intronum']."'";
			$result = $xoopsDB->queryF($query);
			if ($result == false) {
				// Throw error if the query fails.
				redirect_header("intros.php",10,$xoopsDB->error());
				break;
			}
			if (! ($row = $xoopsDB->fetchArray($result)) ) {
				// Throw error if we can't load the row.
				redirect_header("intros.php",10,$xoopsDB->error());
				break;
			}
			if ( isset($_REQUEST['verify']) ) {
				// Remove from intro map
				$query = "DELETE FROM ".$xoopsDB->prefix('uhqiceauth_intros');
				$query .= " WHERE intronum = '".$sane_REQUEST['intronum']."'";
				$result = $xoopsDB->queryF($query);
				if ($result == false) {
					// Throw error if the query fails.
					redirect_header("intros.php",10,$xoopsDB->error());
					break;
				}

				// Remove from intro list
				$query = "DELETE FROM ".$xoopsDB->prefix('uhqiceauth_intromap');
				$query .= " WHERE intronum = '".$row['intronum']."'";
				$result = $xoopsDB->queryF($query);
				if ($result == false) {
					// Throw error if the query fails.
					redirect_header("intros.php",10,$xoopsDB->error());
					break;
				}

				// Delete file
				unlink (XOOPS_ROOT_PATH."/modules/uhq_iceauth/intros/".$row['filename']);
				redirect_header("intros.php",10,_AM_UHQICEAUTH_DELETED." ".$row['filename']);
			} else {
				// Display page w/ basic form.
				xoops_cp_header();
				$mainAdmin = new ModuleAdmin();
				echo $mainAdmin->addNavigation('intros.php');
				uhqiceauth_introdelform(_AM_UHQICEAUTH_INTROS_DELETE,$row);
				include_once __DIR__ . '/admin_footer.php';

			}
		} else {
			// Minimum Parameters not met
			redirect_header("intros.php",10,_AM_UHQICEAUTH_PARAMERR);
		}
		break;
	case "edit" :
		// Check minimum parameters
		if ( isset($sane_REQUEST['intronum'] ) ) {
			// Load Record
			$query = "SELECT * FROM ".$xoopsDB->prefix('uhqiceauth_intros');
			$query .= " WHERE intronum = '".$sane_REQUEST['intronum']."'";
			$result = $xoopsDB->queryF($query);
			if ($result == false) {
				// Throw error and break if the query fails.
				redirect_header("intros.php",10,$xoopsDB->error());
				break;
			}
			if (! ($row = $xoopsDB->fetchArray($result)) ) {
				// Throw error and break if we can't load the row.
				redirect_header("intros.php",10,$xoopsDB->error());
				break;
			}
			if ( isset($_REQUEST['verify']) ) {
				// Process changes

				if ($_FILES['introfile']['error'] != 4) {
					// Process file upload.  (Error 4 = file not uploaded)
					$uploader = new XoopsMediaUploader(XOOPS_ROOT_PATH."/modules/uhq_iceauth/intros",$uhqiceauth_intro_mimes,1048576);
					if ($uploader->fetchMedia($_POST['xoops_upload_file'][0]) ) {
						$uploader->setPrefix('intro-');
						if ( $uploader->upload() ) {
							$filetarget = $uploader->getSavedFileName();
						} else {
							// Throw error and break if upload fails.
							redirect_header("intros.php",10,_AM_UHQICEAUTH_ERR_ULSAVE.$uploader->getErrors());
							break;
						}
					} else {
						// Throw error and break if fetch fails.
						redirect_header("intros.php",10,_AM_UHQICEAUTH_ERR_FETCH.$uploader->getErrors());
						break;
					}
				} else {
					// Keep same file target if there was nothing uploaded.
					$filetarget = $row['filename'];
				}

				// Update DB.
				$query = "UPDATE ".$xoopsDB->prefix('uhqiceauth_intros');
				$query .= " SET codec='".$sane_REQUEST['codec']."', ";
				$query .= " filename='".$filetarget."', ";
				$query .= " description='".$sane_REQUEST['description']."' ";
				$query .= " WHERE intronum='".$sane_REQUEST['intronum']."'";

				$result = $xoopsDB->queryF($query);
				if ($result == false) {
					if ($filetarget != $row['filename']) {
						// Delete the new file if the DB update fails.
					}
					redirect_header("intros.php",10,_AM_UHQICEAUTH_SQLERR.$query);
				} else {
					if ($filetarget != $row['filename']) {
						// Delete the old file if the update passes.
						unlink (XOOPS_ROOT_PATH."/modules/uhq_iceauth/intros/".$row['filename']);
						redirect_header("intros.php",10,_AM_UHQICEAUTH_INTROS_UPDOK.$sane_REQUEST['intronum']."  (".$filetarget.")");
					} else {
						redirect_header("intros.php",10,_AM_UHQICEAUTH_INTROS_UPDOK.$sane_REQUEST['intronum']);
					}
				}
			} else {
				// Display page w/ form
				xoops_cp_header();
				$mainAdmin = new ModuleAdmin();
				echo $mainAdmin->addNavigation('intros.php');
				uhqiceauth_introform(_AM_UHQICEAUTH_INTROS_EDIT,$row,$op);
				include_once __DIR__ . '/admin_footer.php';

			}

		} else {
			redirect_header("intros.php",10,_AM_UHQICEAUTH_PARAMERR);
		}
		break;
	case "play" :
		// Turn off logger on this page.
		$xoopsLogger->activated = false;

		// Check minimum parameters
		if ( isset($sane_REQUEST['intronum'] ) ) {
			// Load Record
			$query = "SELECT * FROM ".$xoopsDB->prefix('uhqiceauth_intros');
			$query .= " WHERE intronum = '".$sane_REQUEST['intronum']."'";
			$result = $xoopsDB->queryF($query);
			if ($result == false) {
				echo $xoopsDB->error();
			} else {
				if ($row = $xoopsDB->fetchArray($result)) {
					if (file_exists("../intros/".$row['filename'])) {
						$data['playurl'] = "/modules/uhq_iceauth/intros/".$row['filename'];
						$data['filename'] = $row['filename'];
						$xoopsTpl->assign('data',$data);
					} else {
						$xoopsTpl->assign('error',_AM_UHQICEAUTH_INTROS_PLAY_NOFILE);
					}
				} else {
					$xoopsTpl->assign('error',_AM_UHQICEAUTH_INTROS_PLAY_NORECORD);
				}
			}
		} else {
			$xoopsTpl->assign('error',_AM_UHQICEAUTH_INTROS_PLAY_NOINTRO);
		}
		$xoopsTpl->display("db:admin/uhqiceauth_introplay.html");
		break;
	case "none" :
	default:
		// Print Header
		xoops_cp_header();
		$mainAdmin = new ModuleAdmin();
		echo $mainAdmin->addNavigation('intros.php');
        $mainAdmin->addItemButton(_AM_UHQICEAUTH_INTROS_ADD, 'intros.php?op=insert', 'add');
        echo $mainAdmin->renderButton("left"); // �right� is default

		$data['incount'] = uhqiceauth_summarycount("IN");

		// See if we have anything first.
		if ( $data['incount'] > 0 ) {
			$query = "SELECT * FROM ".$xoopsDB->prefix('uhqiceauth_intros')." ORDER BY intronum";
			$result = $xoopsDB->queryF($query);
			if ($result == false) {
				$xoospTpl->assign('error',$xoopsDB->error() );
			} else {
				$i=1;
				while ($row = $xoopsDB->fetchArray($result) ) {
					$data['intros'][$i] = $row;
					$data['intros'][$i]['description'] = $myts->displayTarea($row['description'],1);
					$i++;
				}
				$xoopsTpl->assign('data',$data);
			}
		} else {
			$xoopsTpl->assign('data',$data);
		}

		$xoopsTpl->display("db:admin/uhqiceauth_intros.html");
		include_once __DIR__ . '/admin_footer.php';

		break;
}
