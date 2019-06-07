<?php
/**
 * Copyright (c) Enalean, 2019. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Tuleap\Docman\REST\v1;

use Docman_LockFactory;
use Docman_PermissionsManager;
use Docman_SettingsBo;
use Docman_VersionFactory;
use Luracast\Restler\RestException;
use ProjectManager;
use Tuleap\DB\DBFactory;
use Tuleap\DB\DBTransactionExecutorWithConnection;
use Tuleap\Docman\ApprovalTable\ApprovalTableRetriever;
use Tuleap\Docman\ApprovalTable\ApprovalTableUpdateActionChecker;
use Tuleap\Docman\ApprovalTable\Exceptions\ItemHasApprovalTableButNoApprovalActionException;
use Tuleap\Docman\ApprovalTable\Exceptions\ItemHasNoApprovalTableButHasApprovalActionException;
use Tuleap\Docman\DeleteFailedException;
use Tuleap\Docman\Lock\LockChecker;
use Tuleap\Docman\Lock\LockUpdater;
use Tuleap\Docman\REST\v1\Files\CreatedItemFilePropertiesRepresentation;
use Tuleap\Docman\REST\v1\Files\DocmanFilesPATCHRepresentation;
use Tuleap\Docman\REST\v1\Files\DocmanItemFileUpdator;
use Tuleap\Docman\REST\v1\Metadata\HardcodedMetadataObsolescenceDateRetriever;
use Tuleap\Docman\REST\v1\Metadata\HardcodedMetdataObsolescenceDateChecker;
use Tuleap\Docman\REST\v1\Metadata\ItemStatusMapper;
use Tuleap\Docman\Upload\UploadMaxSizeExceededException;
use Tuleap\Docman\Upload\Version\DocumentOnGoingVersionToUploadDAO;
use Tuleap\Docman\Upload\Version\VersionToUploadCreator;
use Tuleap\REST\AuthenticatedResource;
use Tuleap\REST\Header;
use Tuleap\REST\I18NRestException;
use Tuleap\REST\UserManager as RestUserManager;

class DocmanFilesResource extends AuthenticatedResource
{
    /**
     * @var \EventManager
     */
    private $event_manager;
    /**
     * @var RestUserManager
     */
    private $rest_user_manager;
    /**
     * @var DocmanItemsRequestBuilder
     */
    private $request_builder;

    public function __construct()
    {
        $this->rest_user_manager = RestUserManager::build();
        $this->request_builder   = new DocmanItemsRequestBuilder($this->rest_user_manager, ProjectManager::instance());
        $this->event_manager = \EventManager::instance();
    }

    /**
     * @url OPTIONS {id}
     */
    public function optionsDocumentItems(int $id): void
    {
        $this->setHeaders();
    }

    /**
     * Lock a specific file
     *
     * <pre>
     * /!\ This route is under construction and will be subject to changes
     * </pre>
     *
     * @param int $id Id of the file you want lock
     *
     * @throws I18NRestException 400
     * @throws RestException 401
     * @throws I18NRestException 403
     * @throws RestException 404
     *
     * @url    POST {id}/lock
     * @access hybrid
     * @status 201
     *
     */
    public function postLock(int $id): void
    {
        $this->checkAccess();
        $this->setHeadersForLock();

        $current_user = $this->rest_user_manager->getCurrentUser();

        $item_request = $this->request_builder->buildFromItemId($id);
        $item         = $item_request->getItem();
        $project      = $item_request->getProject();

        $this->checkUserCanWrite($project, $current_user, $item);

        $validator = new DocumentBeforeModificationValidatorVisitor(\Docman_File::class);
        $item->accept($validator, []);

        $event_adder = $this->getDocmanItemsEventAdder();
        $event_adder->addLogEvents();
        $event_adder->addNotificationEvents($project);

        $lock_factory = new Docman_LockFactory();
        $lock_checker = new LockChecker($lock_factory);
        $lock_updater = new LockUpdater($lock_factory);

        try {
            $lock_checker->checkItemIsLocked($item, $current_user);
            $is_file_locked = true;
            $lock_updater->updateLockInformation($item, $is_file_locked, $current_user);
        } catch (ExceptionItemIsLockedByAnotherUser $e) {
            throw new I18NRestException(
                403,
                dgettext('tuleap-docman', 'Document is locked by another user.')
            );
        }
    }

    /**
     * @url OPTIONS {id}/lock
     */
    public function optionsIdLock(int $id): void
    {
        $this->setHeadersForLock();
    }

    /**
     * Patch an element of document manager
     *
     * Create a new version of an existing file document
     * <pre>
     * /!\ This route is under construction and will be subject to changes
     * </pre>
     *
     * <pre>
     * approval_table_action should be provided only if item has an existing approval table.<br>
     * Possible values:<br>
     *  * copy: Creates an approval table based on the previous one<br>
     *  * reset: Reset the current approval table<br>
     *  * empty: No approbation needed for the new version of this document<br>
     * </pre>
     *
     * @url    PATCH {id}
     * @access hybrid
     *
     * @param int                            $id             Id of the item
     * @param DocmanFilesPATCHRepresentation $representation {@from body}
     *
     * @return CreatedItemFilePropertiesRepresentation
     *
     * @status 200
     * @throws RestException 400
     * @throws RestException 403
     * @throws RestException 501
     */

    public function patch(int $id, DocmanFilesPATCHRepresentation $representation)
    {
        $this->checkAccess();
        $this->setHeaders();

        $item_request = $this->request_builder->buildFromItemId($id);
        $item         = $item_request->getItem();

        $validator = new DocumentBeforeModificationValidatorVisitor(\Docman_File::class);
        $item->accept($validator, []);

        $current_user = $this->rest_user_manager->getCurrentUser();

        $project = $item_request->getProject();

        $this->checkUserCanWrite($project, $current_user, $item);

        $event_adder = $this->getDocmanItemsEventAdder();
        $event_adder->addLogEvents();
        $event_adder->addNotificationEvents($project);


        $docman_approval_table_retriever = new ApprovalTableRetriever(
            new \Docman_ApprovalTableFactoriesFactory(),
            new Docman_VersionFactory()
        );

        $docman_item_updator = $this->getFileUpdator($project, $docman_approval_table_retriever);

        try {
            $approval_check = new ApprovalTableUpdateActionChecker($docman_approval_table_retriever);
            $approval_check->checkApprovalTableForItem($representation->approval_table_action, $item);
            return $docman_item_updator->updateFile(
                $item,
                $current_user,
                $representation,
                new \DateTimeImmutable()
            );
        } catch (ExceptionItemIsLockedByAnotherUser $exception) {
            throw new I18NRestException(
                403,
                dgettext('tuleap-docman', 'Document is locked by another user.')
            );
        } catch (UploadMaxSizeExceededException $exception) {
            throw new RestException(
                400,
                $exception->getMessage()
            );
        } catch (ItemHasApprovalTableButNoApprovalActionException $exception) {
            throw new I18NRestException(
                400,
                sprintf(
                    dgettext(
                        'tuleap-docman',
                        '%s has an approval table, you must provide an option to have approval table on new version creation.'
                    ),
                    $item->title
                )
            );
        } catch (ItemHasNoApprovalTableButHasApprovalActionException $exception) {
            throw new I18NRestException(
                400,
                dgettext(
                    'tuleap-docman',
                    'Impossible to update a file which already has an approval table without approval action.'
                )
            );
        } catch (Metadata\HardCodedMetadataException $e) {
            throw new I18NRestException(
                400,
                $e->getI18NExceptionMessage()
            );
        }
    }

    /**
     * Delete a file document in the document manager
     *
     * Delete an existing file document
     *
     * @url    DELETE {id}
     * @access hybrid
     *
     * @param int $id Id of the item
     *
     * @status 200
     * @throws RestException 401
     * @throws I18NRestException 403
     * @throws RestException 404
     */
    public function delete(int $id) : void
    {
        $this->checkAccess();
        $this->setHeaders();

        $item_request      = $this->request_builder->buildFromItemId($id);
        $item_to_delete    = $item_request->getItem();
        $current_user      = $this->rest_user_manager->getCurrentUser();
        $project           = $item_request->getProject();
        $validator_visitor = new DocumentBeforeModificationValidatorVisitor(\Docman_File::class);

        $item_to_delete->accept($validator_visitor);

        $event_adder = $this->getDocmanItemsEventAdder();
        $event_adder->addLogEvents();
        $event_adder->addNotificationEvents($project);

        try {
            (new \Docman_ItemFactory())->deleteSubTree($item_to_delete, $current_user, false);
        } catch (DeleteFailedException $exception) {
            throw new I18NRestException(
                403,
                $exception->getI18NExceptionMessage()
            );
        }

        $this->event_manager->processEvent('send_notifications', []);
    }

    private function getDocmanItemsEventAdder(): DocmanItemsEventAdder
    {
        return new DocmanItemsEventAdder($this->event_manager);
    }

    private function setHeaders(): void
    {
        Header::allowOptionsPatchDelete();
    }

    /**
     * @param \Project               $project
     * @param ApprovalTableRetriever $docman_approval_table_retriever
     *
     * @return DocmanItemFileUpdator
     */
    private function getFileUpdator(
        \Project $project,
        ApprovalTableRetriever $docman_approval_table_retriever
    ): DocmanItemFileUpdator {
        $docman_setting_bo                            = new Docman_SettingsBo($project->getGroupId());
        $hardcoded_metadata_obsolescence_date_checker = new HardcodedMetdataObsolescenceDateChecker(
            $docman_setting_bo
        );
        $docman_item_updator                          = new DocmanItemFileUpdator(
            $docman_approval_table_retriever,
            new VersionToUploadCreator(
                new DocumentOnGoingVersionToUploadDAO(),
                new DBTransactionExecutorWithConnection(DBFactory::getMainTuleapDBConnection())
            ),
            new LockChecker(new Docman_LockFactory()),
            new ItemStatusMapper($docman_setting_bo),
            new HardcodedMetadataObsolescenceDateRetriever(
                $hardcoded_metadata_obsolescence_date_checker
            )
        );
        return $docman_item_updator;
    }

    private function setHeadersForLock(): void
    {
        Header::allowOptionsPost();
    }

    /**
     * @param \Project     $project
     * @param \PFUser      $current_user
     * @param \Docman_Item $item
     *
     * @throws I18NRestException
     */
    private function checkUserCanWrite(\Project $project, \PFUser $current_user, \Docman_Item $item): void
    {
        $docman_permission_manager = Docman_PermissionsManager::instance($project->getGroupId());
        if (! $docman_permission_manager->userCanWrite($current_user, $item->getId())) {
            throw new I18NRestException(
                403,
                dgettext('tuleap-docman', 'You are not allowed to write this item.')
            );
        }
    }
}
