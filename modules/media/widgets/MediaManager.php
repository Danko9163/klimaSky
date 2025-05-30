<?php namespace Media\Widgets;

use Url;
use Str;
use Lang;
use File;
use Input;
use Config;
use Request;
use Response;
use Exception;
use BackendAuth;
use SystemException;
use ApplicationException;
use Backend\Classes\WidgetBase;
use Media\Classes\MediaLibrary;
use Media\Classes\MediaLibraryItem;
use October\Rain\Resize\Resizer;
use October\Rain\Filesystem\Definitions as FileDefinitions;
use Form as FormHelper;
use ForbiddenException;

/**
 * MediaManager widget
 *
 * @package october\media
 * @author Alexey Bobkov, Samuel Georges
 */
class MediaManager extends WidgetBase
{
    const FOLDER_ROOT = '/';

    const VIEW_MODE_GRID = 'grid';
    const VIEW_MODE_LIST = 'list';
    const VIEW_MODE_TILES = 'tiles';

    const SELECTION_MODE_NORMAL = 'normal';
    const SELECTION_MODE_FIXED_RATIO = 'fixed-ratio';
    const SELECTION_MODE_FIXED_SIZE = 'fixed-size';

    const FILTER_EVERYTHING = 'everything';

    /**
     * @var string currentFolder
     */
    protected $currentFolder;

    /**
     * @var string brokenImageHash string for the broken image graphic.
     */
    protected $brokenImageHash;

    /**
     * @var bool readOnly determines whether the widget is in readonly mode or not.
     */
    public $readOnly = false;

    /**
     * @var bool bottomToolbar determines whether the bottom toolbar is visible.
     */
    public $bottomToolbar = false;

    /**
     * @var bool cropAndInsertButton determines whether the Crop & Insert button is visible.
     */
    public $cropAndInsertButton = false;

    /**
     * @var string defaultAlias to identify this widget.
     */
    protected $defaultAlias = 'mediamanager';

    /**
     * __construct
     */
    public function __construct($controller, $config = [], $readOnly = false)
    {
        // @deprecated construct arguments should be controller, config only
        // drop readOnly from constructor and adjust code below
        if (is_string($config)) {
            $config = ['alias' => $config];
        }

        $this->readOnly = $config['readOnly'] ?? $readOnly;

        parent::__construct($controller, $config);

        $this->checkUploadPostback();
    }

    /**
     * loadAssets adds widget specific asset files. Use $this->addJs() and $this->addCss()
     * to register new assets to include on the page.
     * @return void
     */
    protected function loadAssets()
    {
        $this->addCssBundle('css/mediamanager.css', 'global');
        $this->addJsBundle('js/mediamanager.js', 'global');
        $this->addJsBundle('js/mediamanager.imagecroppopup.js', 'global');
        $this->addJsBundle('js/mediamanager.popup.js', 'global');
    }

    /**
     * render the widget.
     * @return string
     */
    public function render()
    {
        $this->prepareVars();

        return $this->makePartial('body');
    }

    //
    // AJAX handlers
    //

    /**
     * onSearch perform a search
     * @return array
     */
    public function onSearch()
    {
        $this->setSearchTerm(Input::get('search'));

        $this->prepareVars();

        return [
            '#'.$this->getId('item-list') => $this->makePartial('item-list'),
            '#'.$this->getId('folder-path') => $this->makePartial('folder-path')
        ];
    }

    /**
     * onGoToFolder
     * @return array
     */
    public function onGoToFolder()
    {
        $path = Input::get('path');

        if (Input::get('clearCache')) {
            MediaLibrary::instance()->resetCache();
        }

        if (Input::get('resetSearch')) {
            $this->setSearchTerm(null);
        }

        $this->setCurrentFolder($path);
        $this->prepareVars();

        return [
            '#'.$this->getId('item-list') => $this->makePartial('item-list'),
            '#'.$this->getId('folder-path') => $this->makePartial('folder-path')
        ];
    }

    /**
     * onGenerateThumbnails
     * @return array
     */
    public function onGenerateThumbnails()
    {
        $batch = Input::get('batch');
        if (!is_array($batch)) {
            return;
        }

        $result = [];
        foreach ($batch as $thumbnailInfo) {
            $result[] = $this->generateThumbnail($thumbnailInfo);
        }

        return [
            'generatedThumbnails'=>$result
        ];
    }

    /**
     * onGetSidebarThumbnail
     * @return array
     */
    public function onGetSidebarThumbnail()
    {
        $path = Input::get('path');
        $lastModified = Input::get('lastModified');

        $thumbnailParams = $this->getThumbnailParams();
        $thumbnailParams['width'] = 300;
        $thumbnailParams['height'] = 255;
        $thumbnailParams['mode'] = 'auto';

        $path = MediaLibrary::validatePath($path);

        if (!is_numeric($lastModified)) {
            throw new ApplicationException('Invalid input data');
        }

        // If the thumbnail file exists, just return the thumbnail markup,
        // otherwise generate a new thumbnail.
        $thumbnailPath = $this->thumbnailExists($thumbnailParams, $path, $lastModified);
        if ($thumbnailPath) {
            return [
                'markup' => $this->makePartial('thumbnail-image', [
                    'isError' => $this->thumbnailIsError($thumbnailPath),
                    'imageUrl' => $this->getThumbnailImageUrl($thumbnailPath)
                ])
            ];
        }

        $thumbnailInfo = $thumbnailParams;
        $thumbnailInfo['path'] = $path;
        $thumbnailInfo['lastModified'] = $lastModified;
        $thumbnailInfo['id'] = 'sidebar-thumbnail';

        return $this->generateThumbnail($thumbnailInfo, $thumbnailParams);
    }

    /**
     * onChangeView preference
     * @return array
     */
    public function onChangeView()
    {
        $viewMode = Input::get('view');
        $path = Input::get('path');

        $this->setViewMode($viewMode);
        $this->setCurrentFolder($path);

        $this->prepareVars();

        return [
            '#'.$this->getId('item-list') => $this->makePartial('item-list'),
            '#'.$this->getId('folder-path') => $this->makePartial('folder-path'),
            '#'.$this->getId('view-mode-buttons') => $this->makePartial('view-mode-buttons')
        ];
    }

    /**
     * onSetFilter preference
     * @return array
     */
    public function onSetFilter()
    {
        $filter = Input::get('filter');
        $path = Input::get('path');

        $this->setFilter($filter);
        $this->setCurrentFolder($path);

        $this->prepareVars();

        return [
            '#'.$this->getId('item-list') => $this->makePartial('item-list'),
            '#'.$this->getId('folder-path') => $this->makePartial('folder-path'),
            '#'.$this->getId('filters') => $this->makePartial('filters')
        ];
    }

    /**
     * onSetSorting ets sorting preference
     * @return array
     */
    public function onSetSorting()
    {
        $sortBy = Input::get('sortBy', $this->getSortBy());
        $sortDirection = Input::get('sortDirection', $this->getSortDirection());
        $path = Input::get('path');

        $this->setSortBy($sortBy);
        $this->setSortDirection($sortDirection);
        $this->setCurrentFolder($path);

        $this->prepareVars();

        return [
            '#'.$this->getId('item-list') => $this->makePartial('item-list'),
            '#'.$this->getId('folder-path') => $this->makePartial('folder-path')
        ];
    }

    /**
     * onDeleteItem deletes a library item
     * @return array
     */
    public function onDeleteItem()
    {
        if (!$this->checkHasPermission('mediaDelete')) {
            throw new ForbiddenException;
        }

        $paths = Input::get('paths');

        if (!is_array($paths)) {
            throw new ApplicationException('Invalid input data');
        }

        $library = MediaLibrary::instance();

        $filesToDelete = [];
        foreach ($paths as $pathInfo) {
            $path = array_get($pathInfo, 'path');
            $type = array_get($pathInfo, 'type');

            if (!$path || !$type) {
                throw new ApplicationException('Invalid input data');
            }

            if ($type === MediaLibraryItem::TYPE_FILE) {
                // Add to bulk collection
                $filesToDelete[] = $path;
            }
            elseif ($type === MediaLibraryItem::TYPE_FOLDER) {
                // Delete single folder
                $library->deleteFolder($path);

                /**
                 * @event media.folder.delete
                 * Called after a folder is deleted
                 *
                 * Example usage:
                 *
                 *     Event::listen('media.folder.delete', function ((\Media\Widgets\MediaManager) $mediaWidget, (string) $path) {
                 *         \Log::info($path . " was deleted");
                 *     });
                 *
                 * Or
                 *
                 *     $mediaWidget->bindEvent('folder.delete', function ((string) $path) {
                 *         \Log::info($path . " was deleted");
                 *     });
                 *
                 */
                $this->fireSystemEvent('media.folder.delete', [$path]);
            }
        }

        if (count($filesToDelete) > 0) {
            // Delete collection of files
            $library->deleteFiles($filesToDelete);

            // Extensibility
            foreach ($filesToDelete as $path) {
                /**
                 * @event media.file.delete
                 * Called after a file is deleted
                 *
                 * Example usage:
                 *
                 *     Event::listen('media.file.delete', function ((\Media\Widgets\MediaManager) $mediaWidget, (string) $path) {
                 *         \Log::info($path . " was deleted");
                 *     });
                 *
                 * Or
                 *
                 *     $mediaWidget->bindEvent('file.delete', function ((string) $path) {
                 *         \Log::info($path . " was deleted");
                 *     });
                 *
                 */
                $this->fireSystemEvent('media.file.delete', [$path]);
            }
        }

        $library->resetCache();
        $this->prepareVars();

        return [
            '#'.$this->getId('item-list') => $this->makePartial('item-list')
        ];
    }

    /**
     * onLoadRenamePopup shows the rename item popup
     * @return array
     */
    public function onLoadRenamePopup()
    {
        if (!$this->checkHasPermission('mediaDelete')) {
            throw new ForbiddenException;
        }

        $path = Input::get('path');
        $path = MediaLibrary::validatePath($path);

        $this->vars['originalPath'] = $path;
        $this->vars['name'] = basename($path);
        $this->vars['listId'] = Input::get('listId');
        $this->vars['type'] = Input::get('type');

        return $this->makePartial('rename-form');
    }

    /**
     * onApplyName renames an item
     * @return array
     */
    public function onApplyName()
    {
        if (!$this->checkHasPermission('mediaDelete')) {
            throw new ForbiddenException;
        }

        $newName = trim(Input::get('name'));
        if (!strlen($newName)) {
            throw new ApplicationException(Lang::get('cms::lang.asset.name_cant_be_empty'));
        }

        if (!$this->validateFileName($newName)) {
            throw new ApplicationException(Lang::get('cms::lang.asset.invalid_name'));
        }

        $originalPath = Input::get('originalPath');
        $originalPath = MediaLibrary::validatePath($originalPath);
        $newPath = dirname($originalPath).'/'.$newName;
        $type = Input::get('type');

        if ($type === MediaLibraryItem::TYPE_FILE) {
            // Validate extension
            if (!$this->validateFileType($newName)) {
                throw new ApplicationException(Lang::get('backend::lang.media.type_blocked'));
            }

            // @deprecated media.clean_vectors set default to true in v4
            if (Config::get('media.clean_vectors', false) && $this->isVector($newName)) {
                throw new ApplicationException(Lang::get('backend::lang.media.type_blocked'));
            }

            // Move single file
            MediaLibrary::instance()->moveFile($originalPath, $newPath);

            /**
             * @event media.file.rename
             * Called after a file is renamed / moved
             *
             * Example usage:
             *
             *     Event::listen('media.file.rename', function ((\Media\Widgets\MediaManager) $mediaWidget, (string) $originalPath, (string) $newPath) {
             *         \Log::info($originalPath . " was moved to " . $path);
             *     });
             *
             * Or
             *
             *     $mediaWidget->bindEvent('file.rename', function ((string) $originalPath, (string) $newPath) {
             *         \Log::info($originalPath . " was moved to " . $path);
             *     });
             *
             */
            $this->fireSystemEvent('media.file.rename', [$originalPath, $newPath]);
        }
        else {
            // Move single folder
            MediaLibrary::instance()->moveFolder($originalPath, $newPath);

            /**
             * @event media.folder.rename
             * Called after a folder is renamed / moved
             *
             * Example usage:
             *
             *     Event::listen('media.folder.rename', function ((\Media\Widgets\MediaManager) $mediaWidget, (string) $originalPath, (string) $newPath) {
             *         \Log::info($originalPath . " was moved to " . $path);
             *     });
             *
             * Or
             *
             *     $mediaWidget->bindEvent('folder.rename', function ((string) $originalPath, (string) $newPath) {
             *         \Log::info($originalPath . " was moved to " . $path);
             *     });
             *
             */
            $this->fireSystemEvent('media.folder.rename', [$originalPath, $newPath]);
        }

        MediaLibrary::instance()->resetCache();
    }

    /**
     * onCreateFolder shows the create folder popup
     * @return array
     */
    public function onCreateFolder()
    {
        if (!$this->checkHasPermission('mediaCreate')) {
            throw new ForbiddenException;
        }

        $name = trim(Input::get('name'));
        if (!strlen($name)) {
            throw new ApplicationException(Lang::get('cms::lang.asset.name_cant_be_empty'));
        }

        if (!$this->validateFileName($name)) {
            throw new ApplicationException(Lang::get('cms::lang.asset.invalid_name'));
        }

        $path = Input::get('path');
        $path = MediaLibrary::validatePath($path);

        $newFolderPath = $path.'/'.$name;

        $library = MediaLibrary::instance();

        if ($library->folderExists($newFolderPath)) {
            throw new ApplicationException(Lang::get('backend::lang.media.folder_or_file_exist'));
        }

        /*
         * Create the new folder
         */
        if (!$library->makeFolder($newFolderPath)) {
            throw new ApplicationException(Lang::get('backend::lang.media.error_creating_folder'));
        }

        /**
         * @event media.folder.create
         * Called after a folder is created
         *
         * Example usage:
         *
         *     Event::listen('media.folder.create', function ((\Media\Widgets\MediaManager) $mediaWidget, (string) $newFolderPath) {
         *         \Log::info($newFolderPath . " was created");
         *     });
         *
         * Or
         *
         *     $mediaWidget->bindEvent('folder.create', function ((string) $newFolderPath) {
         *         \Log::info($newFolderPath . " was created");
         *     });
         *
         */
        $this->fireSystemEvent('media.folder.create', [$newFolderPath]);

        $library->resetCache();

        $this->prepareVars();

        return [
            '#'.$this->getId('item-list') => $this->makePartial('item-list')
        ];
    }

    /**
     * onLoadMovePopup shows the move item popup
     * @return array
     */
    public function onLoadMovePopup()
    {
        if (!$this->checkHasPermission('mediaDelete')) {
            throw new ForbiddenException;
        }

        $exclude = Input::get('exclude', []);
        if (!is_array($exclude)) {
            throw new ApplicationException('Invalid input data');
        }

        $folders = MediaLibrary::instance()->listAllDirectories($exclude);

        $folderList = [];
        foreach ($folders as $folder) {
            $path = $folder;

            if ($folder === '/') {
                $name = Lang::get('backend::lang.media.library');
            }
            else {
                $segments = explode('/', $folder);
                $name = str_repeat('&nbsp;', (count($segments)-1)*4).basename($folder);
            }

            $folderList[$path] = $name;
        }

        $this->vars['folders'] = $folderList;
        $this->vars['originalPath'] = Input::get('path');

        return $this->makePartial('move-form');
    }

    /**
     * onMoveItems AJAX handler
     * @return array
     */
    public function onMoveItems()
    {
        if (!$this->checkHasPermission('mediaDelete')) {
            throw new ForbiddenException;
        }

        $dest = trim(Input::get('dest'));
        if (!strlen($dest)) {
            throw new ApplicationException(Lang::get('backend::lang.media.please_select_move_dest'));
        }

        $dest = MediaLibrary::validatePath($dest);
        if ($dest === Input::get('originalPath')) {
            throw new ApplicationException(Lang::get('backend::lang.media.move_dest_src_match'));
        }

        $files = Input::get('files', []);
        if (!is_array($files)) {
            throw new ApplicationException('Invalid input data');
        }

        $folders = Input::get('folders', []);
        if (!is_array($folders)) {
            throw new ApplicationException('Invalid input data');
        }

        $library = MediaLibrary::instance();

        foreach ($files as $path) {
            /*
             * Move a single file
             */
            $library->moveFile($path, $dest.'/'.basename($path));

            /**
             * @event media.file.move
             * Called after a file is moved
             *
             * Example usage:
             *
             *     Event::listen('media.file.move', function ((\Media\Widgets\MediaManager) $mediaWidget, (string) $path, (string) $dest) {
             *         \Log::info($path . " was moved to " . $dest);
             *     });
             *
             * Or
             *
             *     $mediaWidget->bindEvent('file.rename', function ((string) $path, (string) $dest) {
             *         \Log::info($path . " was moved to " . $dest);
             *     });
             *
             */
            $this->fireSystemEvent('media.file.move', [$path, $dest]);
        }

        foreach ($folders as $path) {
            /*
             * Move a single folder
             */
            $library->moveFolder($path, $dest.'/'.basename($path));

            /**
             * @event media.folder.move
             * Called after a folder is moved
             *
             * Example usage:
             *
             *     Event::listen('media.folder.move', function ((\Media\Widgets\MediaManager) $mediaWidget, (string) $path, (string) $dest) {
             *         \Log::info($path . " was moved to " . $dest);
             *     });
             *
             * Or
             *
             *     $mediaWidget->bindEvent('folder.rename', function ((string) $path, (string) $dest) {
             *         \Log::info($path . " was moved to " . $dest);
             *     });
             *
             */
            $this->fireSystemEvent('media.folder.move', [$path, $dest]);
        }

        $library->resetCache();

        $this->prepareVars();

        return [
            '#'.$this->getId('item-list') => $this->makePartial('item-list')
        ];
    }

    /**
     * onSetSidebarVisible AJAX handler
     * @return array
     */
    public function onSetSidebarVisible()
    {
        $visible = post('visible');

        $this->setSidebarVisible($visible);
    }

    /**
     * onLoadPopup starts the image cropping session AJAX handler
     * @return array
     */
    public function onLoadPopup()
    {
        if ($currentFolder = post('media_folder')) {
            $this->currentFolder = MediaLibrary::validatePath($currentFolder);
        }

        $this->bottomToolbar = post('bottom_toolbar', $this->bottomToolbar);

        $this->cropAndInsertButton = post('crop_insert_button', $this->cropAndInsertButton);

        return $this->makePartial('popup-body');
    }

    /**
     * onLoadImageCropPopup for cropping AJAX handler
     * @return array
     */
    public function onLoadImageCropPopup()
    {
        if (!$this->checkHasPermission('mediaCreate')) {
            throw new ForbiddenException;
        }

        $path = post('path');
        $path = MediaLibrary::validatePath($path);
        $cropSessionKey = md5(FormHelper::getSessionKey());
        $selectionParams = $this->getSelectionParams();

        $urlAndSize = $this->getCropEditImageUrlAndSize($path, $cropSessionKey);
        $width = $urlAndSize['dimensions'][0];
        $height = $urlAndSize['dimensions'][1] ?: 1;

        $this->vars['currentSelectionMode'] = $selectionParams['mode'];
        $this->vars['currentSelectionWidth'] = $selectionParams['width'];
        $this->vars['currentSelectionHeight'] = $selectionParams['height'];
        $this->vars['cropSessionKey'] = $cropSessionKey;
        $this->vars['imageUrl'] = $urlAndSize['url'];
        $this->vars['dimensions'] = $urlAndSize['dimensions'];
        $this->vars['originalRatio'] = round($width / $height, 5);
        $this->vars['path'] = $path;

        return $this->makePartial('image-crop-popup-body');
    }

    /**
     * onEndCroppingSession AJAX handler
     * @return array
     */
    public function onEndCroppingSession()
    {
        if (!$this->checkHasPermission('mediaCreate')) {
            throw new ForbiddenException;
        }

        $cropSessionKey = Input::get('cropSessionKey');
        if (!preg_match('/^[0-9a-z]+$/', $cropSessionKey)) {
            throw new ApplicationException('Invalid input data');
        }

        $this->removeCropEditDir($cropSessionKey);
    }

    /**
     * onCropImage AJAX handler
     * @return array
     */
    public function onCropImage()
    {
        if (!$this->checkHasPermission('mediaCreate')) {
            throw new ForbiddenException;
        }

        $imageSrcPath = trim(Input::get('img'));
        $selectionData = Input::get('selection');
        $cropSessionKey = Input::get('cropSessionKey');
        $path = Input::get('path');
        $path = MediaLibrary::validatePath($path);

        if (!strlen($imageSrcPath)) {
            throw new ApplicationException('Invalid input data');
        }

        if (!preg_match('/^[0-9a-z]+$/', $cropSessionKey)) {
            throw new ApplicationException('Invalid input data');
        }

        if (!is_array($selectionData)) {
            throw new ApplicationException('Invalid input data');
        }

        $result = $this->cropImage($imageSrcPath, $selectionData, $cropSessionKey, $path);

        $selectionMode = Input::get('selectionMode');
        $selectionWidth = Input::get('selectionWidth');
        $selectionHeight = Input::get('selectionHeight');

        $this->setSelectionParams($selectionMode, $selectionWidth, $selectionHeight);

        return $result;
    }

    /**
     * onResizeImage AJAX handler
     * @return array
     */
    public function onResizeImage()
    {
        if (!$this->checkHasPermission('mediaCreate')) {
            throw new ForbiddenException;
        }

        $cropSessionKey = Input::get('cropSessionKey');
        if (!preg_match('/^[0-9a-z]+$/', $cropSessionKey)) {
            throw new ApplicationException('Invalid input data');
        }

        $width = trim(Input::get('width'));
        if (!strlen($width) || !ctype_digit($width)) {
            throw new ApplicationException('Invalid input data');
        }

        $height = trim(Input::get('height'));
        if (!strlen($height) || !ctype_digit($height)) {
            throw new ApplicationException('Invalid input data');
        }

        $path = Input::get('path');
        $path = MediaLibrary::validatePath($path);

        $params = [
            'width' => $width,
            'height' => $height
        ];

        return $this->getCropEditImageUrlAndSize($path, $cropSessionKey, $params);
    }

    //
    // Methods for internal use
    //

    /**
     * prepareVars is an internal method to prepare view variables.
     */
    protected function prepareVars()
    {
        clearstatcache();

        $folder = $this->getCurrentFolder();
        $viewMode = $this->getViewMode();
        $filter = $this->getFilter();
        $sortBy = $this->getSortBy();
        $sortDirection = $this->getSortDirection();
        $searchTerm = $this->getSearchTerm();
        $searchMode = strlen($searchTerm) > 0;

        if (!$searchMode) {
            $this->vars['items'] = $this->listFolderItems($folder, $filter, ['by' => $sortBy, 'direction' => $sortDirection]);
        }
        else {
            $this->vars['items'] = $this->findFiles($searchTerm, $filter, ['by' => $sortBy, 'direction' => $sortDirection]);
        }

        $this->vars['currentFolder'] = $folder;
        $this->vars['isRootFolder'] = $folder === self::FOLDER_ROOT;
        $this->vars['pathSegments'] = $this->splitPathToSegments($folder);
        $this->vars['viewMode'] = $viewMode;
        $this->vars['thumbnailParams'] = $this->getThumbnailParams($viewMode);
        $this->vars['currentFilter'] = $filter;
        $this->vars['sortBy'] = $sortBy;
        $this->vars['sortDirection'] = $sortDirection;
        $this->vars['searchMode'] = $searchMode;
        $this->vars['searchTerm'] = $searchTerm;
        $this->vars['sidebarVisible'] = $this->getSidebarVisible();
    }

    /**
     * listFolderItems returns a list of folders and files in a Library folder.
     * @param string $searchTerm
     * @param string $filter
     * @param string $sortBy
     * @param array[Media\Classes\MediaLibraryItem]
     */
    protected function listFolderItems($folder, $filter, $sortBy)
    {
        $filter = $filter !== self::FILTER_EVERYTHING ? $filter : null;

        return MediaLibrary::instance()->listFolderContents($folder, $sortBy, $filter);
    }

    /**
     * findFiles from within the media library based on supplied criteria,
     * returns an array of MediaLibraryItem objects.
     * @param string $searchTerm
     * @param string $filter
     * @param string $sortBy
     * @param array[Media\Classes\MediaLibraryItem]
     */
    protected function findFiles($searchTerm, $filter, $sortBy)
    {
        $filter = $filter !== self::FILTER_EVERYTHING ? $filter : null;

        return MediaLibrary::instance()->findFiles($searchTerm, $sortBy, $filter);
    }

    /**
     * setCurrentFolder sets the user current folder from the session state
     * @param string $path
     * @return void
     */
    protected function setCurrentFolder($path)
    {
        $path = MediaLibrary::validatePath($path);

        $this->putSession('media_folder', $path);

        $this->currentFolder = $path;
    }

    /**
     * getCurrentFolder gets the user current folder from the session state
     * @return string
     */
    protected function getCurrentFolder()
    {
        if ($this->currentFolder) {
            return $this->currentFolder;
        }

        return $this->currentFolder = $this->getSession('media_folder', self::FOLDER_ROOT);
    }

    /**
     * setFilter sets the user filter from the session state
     * @param string $filter
     * @return void
     */
    protected function setFilter($filter)
    {
        if (!in_array($filter, [
            self::FILTER_EVERYTHING,
            MediaLibraryItem::FILE_TYPE_IMAGE,
            MediaLibraryItem::FILE_TYPE_AUDIO,
            MediaLibraryItem::FILE_TYPE_DOCUMENT,
            MediaLibraryItem::FILE_TYPE_VIDEO
        ])) {
            throw new ApplicationException('Invalid input data');
        }

        $this->putSession('media_filter', $filter);
    }

    /**
     * getFilter gets the user filter from the session state
     * @return string
     */
    protected function getFilter()
    {
        return $this->getSession('media_filter', self::FILTER_EVERYTHING);
    }

    /**
     * setSearchTerm sets the user search term from the session state
     * @param string $searchTerm
     * @return void
     */
    protected function setSearchTerm($searchTerm)
    {
        $this->putSession('media_search', trim($searchTerm));
    }

    /**
     * getSearchTerm gets the user search term from the session state
     * @return string
     */
    protected function getSearchTerm()
    {
        return $this->getSession('media_search', null);
    }

    /**
     * setSortBy sets the user sort column from the session state
     * @param string $sortBy
     * @return void
     */
    protected function setSortBy($sortBy)
    {
        if (!in_array($sortBy, [
            MediaLibrary::SORT_BY_TITLE,
            MediaLibrary::SORT_BY_SIZE,
            MediaLibrary::SORT_BY_MODIFIED
        ])) {
            throw new ApplicationException('Invalid input data');
        }

        $this->putSession('media_sort_by', $sortBy);
    }

    /**
     * getSortBy gets the user sort column from the session state
     * @return string
     */
    protected function getSortBy()
    {
        return $this->getSession('media_sort_by', MediaLibrary::SORT_BY_TITLE);
    }

    /**
     * setSortDirection sets the user sort direction from the session state
     * @param string $sortDirection
     * @return void
     */
    protected function setSortDirection($sortDirection)
    {
        if (!in_array($sortDirection, [
            MediaLibrary::SORT_DIRECTION_ASC,
            MediaLibrary::SORT_DIRECTION_DESC
        ])) {
            throw new ApplicationException('Invalid input data');
        }

        $this->putSession('media_sort_direction', $sortDirection);
    }

    /**
     * getSortDirection gets the user sort direction from the session state
     * @return string
     */
    protected function getSortDirection()
    {
        return $this->getSession('media_sort_direction', MediaLibrary::SORT_DIRECTION_ASC);
    }

    /**
     * getSelectionParams gets the user selection parameters from the session state
     * @return array
     */
    protected function getSelectionParams()
    {
        $result = $this->getSession('media_crop_selection_params');

        if ($result) {
            if (!isset($result['mode'])) {
                $result['mode'] = self::SELECTION_MODE_NORMAL;
            }

            if (!isset($result['width'])) {
                $result['width'] = null;
            }

            if (!isset($result['height'])) {
                $result['height'] = null;
            }

            return $result;
        }

        return [
            'mode'   => self::SELECTION_MODE_NORMAL,
            'width'  => null,
            'height' => null
        ];
    }

    /**
     * setSelectionParams stores the user selection parameters in the session state
     * @param string $selectionMode
     * @param int $selectionWidth
     * @param int $selectionHeight
     * @return void
     */
    protected function setSelectionParams($selectionMode, $selectionWidth, $selectionHeight)
    {
        if (!in_array($selectionMode, [
            self::SELECTION_MODE_NORMAL,
            self::SELECTION_MODE_FIXED_RATIO,
            self::SELECTION_MODE_FIXED_SIZE
        ])) {
            throw new ApplicationException('Invalid input data');
        }

        if (strlen($selectionWidth) && !ctype_digit($selectionWidth)) {
            throw new ApplicationException('Invalid input data');
        }

        if (strlen($selectionHeight) && !ctype_digit($selectionHeight)) {
            throw new ApplicationException('Invalid input data');
        }

        $this->putSession('media_crop_selection_params', [
            'mode'   => $selectionMode,
            'width'  => $selectionWidth,
            'height' => $selectionHeight
        ]);
    }

    /**
     * setSidebarVisible state
     * @param bool $visible
     * @return void
     */
    protected function setSidebarVisible($visible)
    {
        $this->putSession('sidebar_visible', !!$visible);
    }

    /**
     * getSidebarVisible checks if the sidebar is visible
     * @return bool
     */
    protected function getSidebarVisible()
    {
        return $this->getSession('sidebar_visible', true);
    }

    /**
     * itemTypeToIconClass returns an icon for the item type
     * @param Media\Classes\MediaLibraryItem $item
     * @param string $itemType
     * @return string
     */
    protected function itemTypeToIconClass($item, $itemType)
    {
        if ($item->type === MediaLibraryItem::TYPE_FOLDER) {
            return 'icon-folder';
        }

        switch ($itemType) {
            case MediaLibraryItem::FILE_TYPE_IMAGE:
                return "icon-photo";
            case MediaLibraryItem::FILE_TYPE_VIDEO:
                return "icon-video-camera";
            case MediaLibraryItem::FILE_TYPE_AUDIO:
                return "icon-volume-up";
            default:
                return "icon-file";
        }
    }

    /**
     * splitPathToSegments
     * @param string $path
     * @return array
     */
    protected function splitPathToSegments($path)
    {
        $path = MediaLibrary::validatePath($path, true);
        $path = explode('/', ltrim($path, '/'));

        $result = [];
        while (count($path) > 0) {
            $folder = array_pop($path);

            $result[$folder] = implode('/', $path).'/'.$folder;
            if (substr($result[$folder], 0, 1) !== '/') {
                $result[$folder] = '/'.$result[$folder];
            }
        }

        return array_reverse($result, true);
    }

    /**
     * setViewMode stores a view mode in the session
     * @param string $viewMode
     * @return void
     */
    protected function setViewMode($viewMode)
    {
        if (!in_array($viewMode, [
            self::VIEW_MODE_GRID,
            self::VIEW_MODE_LIST,
            self::VIEW_MODE_TILES
        ])) {
            throw new ApplicationException('Invalid input data');
        }

        $this->putSession('view_mode', $viewMode);
    }

    /**
     * getViewMode returns the current view mode stored in the session
     * @return string
     */
    protected function getViewMode()
    {
        return $this->getSession('view_mode', self::VIEW_MODE_GRID);
    }

    /**
     * getThumbnailParams returns thumbnail parameters
     * @param string $viewMode
     * @return array
     */
    protected function getThumbnailParams($viewMode = null)
    {
        $result = [
            'mode' => 'crop'
        ];

        if ($viewMode) {
            if ($viewMode === self::VIEW_MODE_LIST) {
                $result['width'] = 75;
                $result['height'] = 75;
            }
            else {
                $result['width'] = 165;
                $result['height'] = 165;
            }
        }

        return $result;
    }

    /**
     * getThumbnailImagePath generatess a thumbnail image path
     * @param array|null $thumbnailParams
     * @param string $itemPath
     * @param int $lastModified
     * @return string
     */
    protected function getThumbnailImagePath($thumbnailParams, $itemPath, $lastModified)
    {
        $itemSignature = md5($itemPath).$lastModified;

        $thumbFile = 'thumb_' .
            $itemSignature . '_' .
            $thumbnailParams['width'] . 'x' .
            $thumbnailParams['height'] . '_' .
            $thumbnailParams['mode'] . '.' .
            $this->getThumbnailImageExtension($itemPath);

        $partition = implode('/', array_slice(str_split($itemSignature, 3), 0, 3)) . '/';

        return $this->getThumbnailDirectory().$partition.$thumbFile;
    }

    /**
     * getThumbnailImageExtension
     * @param string $itemPath
     * @return string
     */
    protected function getThumbnailImageExtension($itemPath)
    {
        $extension = pathinfo($itemPath, PATHINFO_EXTENSION);

        if (in_array($extension, ['png', 'gif', 'webp'])) {
            return $extension;
        }

        return 'jpg';
    }

    /**
     * getThumbnailImageUrl returns the URL to a thumbnail
     * @param string $imagePath
     * @return string
     */
    protected function getThumbnailImageUrl($imagePath)
    {
        $fullPath = temp_path(ltrim($imagePath, '/'));

        return Config::get('system.relative_links') === true
            ? Url::toRelative(File::localToPublic($fullPath))
            : Url::asset(File::localToPublic($fullPath));
    }

    /**
     * thumbnailExists checks if a thumbnail exists
     * @param array|null $thumbnailParams
     * @param string $itemPath
     * @param int $lastModified
     * @return bool
     */
    protected function thumbnailExists($thumbnailParams, $itemPath, $lastModified)
    {
        $thumbnailPath = $this->getThumbnailImagePath($thumbnailParams, $itemPath, $lastModified);

        $fullPath = temp_path(ltrim($thumbnailPath, '/'));

        if (File::exists($fullPath)) {
            return $thumbnailPath;
        }

        return false;
    }

    /**
     * thumbnailIsError checks if a thumbnail has caused an error
     * @param string $thumbnailPath
     * @return bool
     */
    protected function thumbnailIsError($thumbnailPath)
    {
        $fullPath = temp_path(ltrim($thumbnailPath, '/'));
        return hash_file('crc32', $fullPath) === $this->getBrokenImageHash();
    }

    /**
     * getLocalTempFilePath
     * @param string $fileName
     * @return string
     */
    protected function getLocalTempFilePath($fileName)
    {
        $fileName = md5($fileName.uniqid().microtime());
        $path = temp_path('media');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        return $path.'/'.$fileName;
    }

    /**
     * getThumbnailDirectory
     * @return string
     */
    protected function getThumbnailDirectory()
    {
        return MediaLibrary::validatePath(
            Config::get('media.thumb_folder', 'public'),
            true
        ) . '/';
    }

    /**
     * getPlaceholderId
     * @param Media\Classes\MediaLibraryItem $item
     * @return string
     */
    protected function getPlaceholderId($item)
    {
        return 'placeholder'.md5($item->path.'-'.$item->lastModified.uniqid(microtime()));
    }

    /**
     * generateThumbnail
     * @param array $thumbnailInfo
     * @param array|null $thumbnailParams
     * @return array
     */
    protected function generateThumbnail($thumbnailInfo, $thumbnailParams = null)
    {
        $tempFilePath = null;
        $fullThumbnailPath = null;
        $thumbnailPath = null;
        $markup = null;

        try {
            $path = $thumbnailInfo['path'];

            if ($this->isVector($path)) {
                $markup = $this->makePartial('thumbnail-image', [
                    'isError' => false,
                    'imageUrl' => Url::to(Config::get('filesystems.disks.media.url') . $thumbnailInfo['path'])
                ]);
            }
            else {
                // Get and validate input data
                $width = $thumbnailInfo['width'];
                $height = $thumbnailInfo['height'];
                $lastModified = $thumbnailInfo['lastModified'];

                if (!is_numeric($width) || !is_numeric($height) || !is_numeric($lastModified)) {
                    throw new ApplicationException('Invalid input data');
                }

                if (!$thumbnailParams) {
                    $thumbnailParams = $this->getThumbnailParams();
                    $thumbnailParams['width'] = $width;
                    $thumbnailParams['height'] = $height;
                }

                $thumbnailPath = $this->getThumbnailImagePath($thumbnailParams, $path, $lastModified);
                $fullThumbnailPath = temp_path(ltrim($thumbnailPath, '/'));

                // Save the file locally
                $library = MediaLibrary::instance();
                $tempFilePath = $this->getLocalTempFilePath($path);

                if (!@File::put($tempFilePath, $library->get($path))) {
                    throw new SystemException('Error saving remote file to a temporary location');
                }

                // Resize the thumbnail and save to the thumbnails directory
                $this->resizeImage($fullThumbnailPath, $thumbnailParams, $tempFilePath);

                // Delete the temporary file
                File::delete($tempFilePath);
                $markup = $this->makePartial('thumbnail-image', [
                    'isError' => false,
                    'imageUrl' => $this->getThumbnailImageUrl($thumbnailPath)
                ]);
            }
        }
        catch (Exception $ex) {
            if ($tempFilePath) {
                File::delete($tempFilePath);
            }

            if ($fullThumbnailPath) {
                $this->copyBrokenImage($fullThumbnailPath);
            }

            $markup = $this->makePartial('thumbnail-image', ['isError' => true]);

            // @todo We need to log all types of exceptions here
            traceLog($ex->getMessage());
        }

        if ($markup && ($id = $thumbnailInfo['id'])) {
            return [
                'id' => $id,
                'markup' => $markup
            ];
        }
    }

    /**
     * resizeImage
     * @param string $fullThumbnailPath
     * @param array $thumbnailParams
     * @param string $tempFilePath
     * @return void
     */
    protected function resizeImage($fullThumbnailPath, $thumbnailParams, $tempFilePath)
    {
        $thumbnailDir = dirname($fullThumbnailPath);
        if (
            !File::isDirectory($thumbnailDir)
            && File::makeDirectory($thumbnailDir, 0755, true) === false
        ) {
            throw new SystemException('Error creating thumbnail directory');
        }

        $targetDimensions = $this->getTargetDimensions($thumbnailParams['width'], $thumbnailParams['height'], $tempFilePath);

        $targetWidth = $targetDimensions[0];
        $targetHeight = $targetDimensions[1];

        Resizer::open($tempFilePath)
            ->resize($targetWidth, $targetHeight, [
                'mode'   => $thumbnailParams['mode'],
                'offset' => [0, 0]
            ])
            ->save($fullThumbnailPath)
        ;

        File::chmod($fullThumbnailPath);
    }

    /**
     * getBrokenImagePath returns the path for the broken image graphic
     * @return string
     */
    protected function getBrokenImagePath()
    {
        return __DIR__.'/mediamanager/assets/images/broken-thumbnail.gif';
    }

    /**
     * getBrokenImageHash returns a CRC32 hash for a broken image
     * @return string
     */
    protected function getBrokenImageHash()
    {
        if ($this->brokenImageHash) {
            return $this->brokenImageHash;
        }

        $fullPath = $this->getBrokenImagePath();

        return $this->brokenImageHash = hash_file('crc32', $fullPath);
    }

    /**
     * copyBrokenImage to destination
     * @param string $path
     * @return void
     */
    protected function copyBrokenImage($path)
    {
        try {
            $thumbnailDir = dirname($path);
            if (!File::isDirectory($thumbnailDir) && File::makeDirectory($thumbnailDir, 0755, true) === false) {
                return;
            }

            File::copy($this->getBrokenImagePath(), $path);
        }
        catch (Exception $ex) {
            traceLog($ex->getMessage());
        }
    }

    /**
     * getTargetDimensions
     * @param int $width
     * @param int $height
     * @param string $originalImagePath
     * @return void
     */
    protected function getTargetDimensions($width, $height, $originalImagePath)
    {
        $originalDimensions = [$width, $height];

        try {
            $dimensions = getimagesize($originalImagePath);
            if (!$dimensions) {
                return $originalDimensions;
            }

            if ($dimensions[0] > $width || $dimensions[1] > $height) {
                return $originalDimensions;
            }

            return $dimensions;
        }
        catch (Exception $ex) {
            return $originalDimensions;
        }
    }

    /**
     * checkUploadPostback detects the upload post flag.
     */
    protected function checkUploadPostback()
    {
        if ($this->readOnly) {
            return;
        }

        $fileName = null;
        $quickMode = false;

        if (
            (!($uniqueId = Request::header('X-OCTOBER-FILEUPLOAD')) || $uniqueId !== $this->getId()) &&
            (!$quickMode = post('X_OCTOBER_MEDIA_MANAGER_QUICK_UPLOAD'))
        ) {
            return;
        }

        if (!$this->checkHasPermission('mediaCreate')) {
            throw new ForbiddenException;
        }

        if (!Input::hasFile('file_data')) {
            throw new ApplicationException('File missing from request');
        }

        try {
            $uploadedFile = files('file_data');

            /**
             * @event media.file.beforeUpload
             * Called before a file is uploaded
             *
             * Example usage:
             *
             *     Event::listen('media.file.beforeUpload', function ((\Symfony\Component\HttpFoundation\File\UploadedFile) $uploadedFile) {
             *         \Log::info($path . " was uploaded.");
             *     });
             *
             * Or
             *
             *     $mediaWidget->bindEvent('file.beforeUpload', function ((\Symfony\Component\HttpFoundation\File\UploadedFile) $uploadedFile) {
             *         \Log::info($path . " was uploaded");
             *     });
             *
             */
            $this->fireSystemEvent('media.file.beforeUpload', [$uploadedFile]);

            // Convert uppercase file extensions to lowercase
            $fileName = $uploadedFile->getClientOriginalName();
            $extension = strtolower($uploadedFile->getClientOriginalExtension());
            $fileName = File::name($fileName).'.'.$extension;

            // File name contains non-latin characters, attempt to slug the value
            $autoRename = Config::get('media.auto_rename') === 'slug';
            if ($autoRename || !$this->validateFileName($fileName)) {
                $fileNameClean = $this->slugFileName(File::name($fileName));
                $fileName = "{$fileNameClean}.{$extension}";
            }

            // Check for unsafe file extensions
            if (!$this->validateFileType($fileName)) {
                throw new ApplicationException(Lang::get('backend::lang.media.type_blocked'));
            }

            // See mime type handling in the asset manager
            if (!$uploadedFile->isValid()) {
                throw new ApplicationException($uploadedFile->getErrorMessage());
            }

            $path = $quickMode ? '/uploaded-files' : Input::get('path');
            $path = MediaLibrary::validatePath($path);
            $filePath = $path.'/'.$fileName;

            // getRealPath() can be empty for some environments (IIS)
            $realPath = empty(trim($uploadedFile->getRealPath()))
                ? $uploadedFile->getPath() . DIRECTORY_SEPARATOR . $uploadedFile->getFileName()
                : $uploadedFile->getRealPath();

            // Cannot overwrite files
            if (!$this->checkHasPermission('mediaDelete') && MediaLibrary::instance()->has($filePath)) {
                throw new ApplicationException(__('A media file already exists at this location, please upload using a different filename.'));
            }

            // Check and clean vector files
            // @todo use streaming like file objects
            $contents = File::get($realPath);
            // @deprecated media.clean_vectors set default to true in v4
            if ($extension === 'svg' && Config::get('media.clean_vectors', false)) {
                // @todo File::cleanVector() helper might be helpful here to clean temporary file in place
                $contents = \Html::cleanVector($contents);
            }

            // Write file to disk
            MediaLibrary::instance()->put(
                $filePath,
                $contents
            );

            /**
             * @event media.file.upload
             * Called after a file is uploaded
             *
             * Example usage:
             *
             *     Event::listen('media.file.upload', function ((\Media\Widgets\MediaManager) $mediaWidget, (string) &$path, (\Symfony\Component\HttpFoundation\File\UploadedFile) $uploadedFile) {
             *         \Log::info($path . " was uploaded.");
             *     });
             *
             * Or
             *
             *     $mediaWidget->bindEvent('file.upload', function ((string) &$path, (\Symfony\Component\HttpFoundation\File\UploadedFile) $uploadedFile) {
             *         \Log::info($path . " was uploaded");
             *     });
             *
             */
            $this->fireSystemEvent('media.file.upload', [&$filePath, $uploadedFile]);

            $response = Response::make([
                'link' => MediaLibrary::url($filePath),
                'result' => 'success'
            ]);
        }
        catch (Exception $ex) {
            $response = Response::make($ex->getMessage(), 400);
        }

        // Override the controller response
        $this->controller->setResponse($response);
    }

    /**
     * validateFileName validates a proposed media item file name.
     * @param string $name
     * @return bool
     */
    protected function validateFileName(string $name): bool
    {
        if (!preg_match('/^[\w@.\s_\-]+$/iu', $name)) {
            return false;
        }

        if (str_contains($name, '..')) {
            return false;
        }

        return true;
    }

    /**
     * validateFileType checks for blocked / unsafe file extensions
     * @param string
     * @return bool
     */
    protected function validateFileType($name)
    {
        $extension = strtolower(File::extension($name));

        $allowedFileTypes = FileDefinitions::get('default_extensions');

        if (!in_array($extension, $allowedFileTypes)) {
            return false;
        }

        return true;
    }

    /**
     * slugFileName creates a slug form the string. A modified version of Str::slug
     * with the main difference that it accepts @-signs
     * @param string $name
     * @return string
     */
    protected function slugFileName(string $name): string
    {
        $title = Str::ascii($name);

        // Convert all dashes/underscores into separator
        $flip = $separator = '-';
        $title = preg_replace('!['.preg_quote($flip).']+!u', $separator, $title);

        // Replace all underscores with the separator
        $title = preg_replace('!['.preg_quote('_').']+!u', $separator, $title);

        // Remove all characters that are not the separator, letters, numbers, whitespace or @.
        $title = preg_replace('![^'.preg_quote($separator).'\pL\pN\s@]+!u', '', mb_strtolower($title));

        // Replace all separator characters and whitespace by a single separator
        $title = preg_replace('!['.preg_quote($separator).'\s]+!u', $separator, $title);

        return trim($title, $separator);
    }

    //
    // Cropping
    //

    /**
     * getCropSessionDirPath returns the crop session working directory path
     * @param string $cropSessionKey
     * @return string
     */
    protected function getCropSessionDirPath($cropSessionKey)
    {
        return $this->getThumbnailDirectory().'edit-crop-'.$cropSessionKey;
    }

    /**
     * getCropEditImageUrlAndSize prepares an image for cropping and returns payload containing a URL
     * @param string $path
     * @param string $cropSessionKey
     * @param array $params
     * @return array
     */
    protected function getCropEditImageUrlAndSize($path, $cropSessionKey, $params = null)
    {
        $sessionDirectoryPath = $this->getCropSessionDirPath($cropSessionKey);
        $fullSessionDirectoryPath = temp_path($sessionDirectoryPath);
        $sessionDirectoryCreated = false;

        if (!File::isDirectory($fullSessionDirectoryPath)) {
            File::makeDirectory($fullSessionDirectoryPath, 0755, true, true);
            $sessionDirectoryCreated = true;
        }

        $tempFilePath = null;

        try {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $library = MediaLibrary::instance();
            $originalThumbFileName = 'original.'.$extension;

            // If the target dimensions are not provided, save the original image to the
            // crop session directory and return its URL.
            if (!$params) {
                $tempFilePath = $fullSessionDirectoryPath.'/'.$originalThumbFileName;

                if (!@File::put($tempFilePath, $library->get($path))) {
                    throw new SystemException('Error saving remote file to a temporary location.');
                }

                $url = $this->getThumbnailImageUrl($sessionDirectoryPath.'/'.$originalThumbFileName);
                $dimensions = getimagesize($tempFilePath);

                return [
                    'url' => $url,
                    'dimensions' => $dimensions
                ];
            }

            // If the target dimensions are provided, resize the original image and
            // return its URL and dimensions.
            $originalFilePath = $fullSessionDirectoryPath.'/'.$originalThumbFileName;
            if (!File::isFile($originalFilePath)) {
                throw new SystemException('The original image is not found in the cropping session directory.');
            }

            $resizedThumbFileName = 'resized-'.$params['width'].'-'.$params['height'].'.'.$extension;
            $tempFilePath = $fullSessionDirectoryPath.'/'.$resizedThumbFileName;

            Resizer::open($originalFilePath)
                ->resize($params['width'], $params['height'], [
                    'mode' => 'exact'
                ])
                ->save($tempFilePath)
            ;

            $url = $this->getThumbnailImageUrl($sessionDirectoryPath.'/'.$resizedThumbFileName);
            $dimensions = getimagesize($tempFilePath);

            return [
                'url' => $url,
                'dimensions' => $dimensions
            ];
        }
        catch (Exception $ex) {
            if ($sessionDirectoryCreated) {
                @File::deleteDirectory($fullSessionDirectoryPath);
            }

            if ($tempFilePath) {
                File::delete($tempFilePath);
            }

            throw $ex;
        }
    }

    /**
     * removeCropEditDir cleans up the directory used for cropping based on the session key
     * @param string $cropSessionKey
     * @return void
     */
    protected function removeCropEditDir($cropSessionKey)
    {
        $sessionDirectoryPath = $this->getCropSessionDirPath($cropSessionKey);
        $fullSessionDirectoryPath = temp_path($sessionDirectoryPath);

        if (File::isDirectory($fullSessionDirectoryPath)) {
            @File::deleteDirectory($fullSessionDirectoryPath);
        }
    }

    /**
     * cropImage is business logic to crop a media library image
     * @param string $imageSrcPath
     * @param array $selectionData
     * @param string $cropSessionKey
     * @param string $path
     * @return array
     */
    protected function cropImage($imageSrcPath, $selectionData, $cropSessionKey, $path)
    {
        $originalFileName = basename($path);

        $path = rtrim(dirname($path), '/').'/';
        $fileName = basename($imageSrcPath);

        if (
            strpos($fileName, '..') !== false ||
            strpos($fileName, '/') !== false ||
            strpos($fileName, '\\') !== false
        ) {
            throw new SystemException('Invalid image file name.');
        }

        $selectionParams = ['x', 'y', 'w', 'h'];

        foreach ($selectionParams as $paramName) {
            if (!array_key_exists($paramName, $selectionData)) {
                throw new SystemException('Invalid selection data.');
            }

            if (!is_numeric($selectionData[$paramName])) {
                throw new SystemException('Invalid selection data.');
            }

            $selectionData[$paramName] = (int) $selectionData[$paramName];
        }

        $sessionDirectoryPath = $this->getCropSessionDirPath($cropSessionKey);
        $fullSessionDirectoryPath = temp_path($sessionDirectoryPath);

        if (!File::isDirectory($fullSessionDirectoryPath)) {
            throw new SystemException('The image editing session is not found.');
        }

        // Find the image on the disk and resize it
        $imagePath = $fullSessionDirectoryPath.'/'.$fileName;
        if (!File::isFile($imagePath)) {
            throw new SystemException('The image is not found on the disk.');
        }

        $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);

        $targetImageName = basename($originalFileName, '.'.$extension).'-'
            .$selectionData['x'].'-'
            .$selectionData['y'].'-'
            .$selectionData['w'].'-'
            .$selectionData['h'].'-';

        $targetImageName .= time();
        $targetImageName .= '.'.$extension;

        $targetTmpPath = $fullSessionDirectoryPath.'/'.$targetImageName;

        // Crop the image, otherwise copy original to target destination.
        if ($selectionData['w'] === 0 || $selectionData['h'] === 0) {
            File::copy($imagePath, $targetTmpPath);
        }
        else {
            Resizer::open($imagePath)
                ->crop(
                    $selectionData['x'],
                    $selectionData['y'],
                    $selectionData['w'],
                    $selectionData['h'],
                    $selectionData['w'],
                    $selectionData['h']
                )
                ->save($targetTmpPath)
            ;
        }

        // Upload the cropped file to the Library
        $targetFolder = $path.'cropped-images';
        $targetPath = $targetFolder.'/'.$targetImageName;

        $library = MediaLibrary::instance();
        $library->putFile($targetPath, $targetTmpPath);

        return [
            'publicUrl' => $library->getPathUrl($targetPath),
            'thumbUrl' => $library->getPathUrl($targetPath),
            'documentType' => MediaLibraryItem::FILE_TYPE_IMAGE,
            'itemType' => MediaLibraryItem::TYPE_FILE,
            'path' => $targetPath,
            'title' => $targetImageName,
            'folder' => $targetFolder
        ];
    }

    /**
     * isVector detects if image is vector graphic (SVG)
     * @param string $path
     * @return bool
     */
    protected function isVector($path)
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg';
    }

    /**
     * checkHasPermission checks if a custom permission has been specified
     */
    protected function checkHasPermission(string $name)
    {
        if ($this->readOnly) {
            return false;
        }

        $foundKey = $name === 'mediaCreate' ? 'media.library.create' : 'media.library.delete';

        return $foundKey ? BackendAuth::userHasAccess($foundKey) : true;
    }
}
