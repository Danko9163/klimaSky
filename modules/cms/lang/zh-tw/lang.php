<?php return [
  'cms_object' => [
    'invalid_file' => '不合法的檔案名: :name. 檔案名只能包括字母或數字, _, - 和 .. 一些正確的檔案名: page.htm, page, subdirectory/page',
    'invalid_property' => '屬性 \':name\' 不能設定',
    'file_already_exists' => '檔案 \':name\' 已經存在.',
    'error_saving' => '保存檔案 \':name\' 錯誤. 請檢查寫權限.',
    'error_creating_directory' => '建立檔案夾 :name 錯誤. 請檢查寫權限.',
    'invalid_file_extension' => '不合法的檔案擴展: :invalid. 允許的擴展: :allowed.',
    'error_deleting' => '刪除模板檔案 \':name\' 錯誤. 請檢查寫權限.',
    'delete_success' => '模板成功刪除: :count.',
    'file_name_required' => '需要檔案名字串.',
  ],
  'dashboard' => [
    'active_theme' => [
      'online' => '在線',
      'maintenance' => '維護中',
    ],
  ],
  'theme' => [
    'active' => [
      'not_set' => '活動主題沒設定.',
      'not_found' => '活動主題找不到.',
    ],
    'edit' => [
      'not_set' => '編輯主題沒設定.',
      'not_found' => '編輯主題沒找到.',
      'not_match' => '您嘗試訪問的對象不屬於正在編輯的主題. 請重載頁面.',
    ],
  ],
  'maintenance' => [],
  'page' => [
    'not_found_name' => '頁面 \':name\' 找不到',
    'not_found' => [
      'label' => '頁面找不到',
      'help' => '請求的頁面找不到.',
    ],
    'custom_error' => [
      'label' => '頁面錯誤',
      'help' => '很抱歉, 有一些地方發生了錯誤導致頁面不能顯示.',
    ],
    'menu_label' => '頁面',
    'unsaved_label' => '未保存頁面',
    'no_list_records' => '找不到頁面',
    'new' => '新頁面',
    'invalid_url' => '不合法的URL格式. URL可以正斜槓開頭, 包含數字, 拉丁字母和下面的字元: ._-[]:?|/+*^$',
    'delete_confirm_multiple' => '真的想要刪除選擇的頁面嗎?',
    'delete_confirm_single' => '真的想要刪除這個頁面嗎?',
    'no_layout' => '-- 沒有佈局 --',
    'title' => '頁面標題',
    'url' => '頁面網址',
    'file_name' => '頁面檔案名稱',
  ],
  'layout' => [
    'not_found_name' => '佈局 \':name\' 找不到',
    'menu_label' => '佈局',
    'unsaved_label' => '未保存佈局',
    'no_list_records' => '找不到佈局',
    'new' => '新佈局',
    'delete_confirm_multiple' => '您真的想要刪除選取的佈局?',
    'delete_confirm_single' => '您真的想要刪除這個佈局?',
  ],
  'partial' => [
    'not_found_name' => '部件 \':name\' 找不到.',
    'invalid_name' => '不合法的部件名: :name.',
    'menu_label' => '部件',
    'unsaved_label' => '未保存的部件',
    'no_list_records' => '找不到部件',
    'delete_confirm_multiple' => '您真的想要刪除選擇的部件?',
    'delete_confirm_single' => '您真的想要刪除這個部件?',
    'new' => '新部件',
  ],
  'content' => [
    'not_found_name' => '內容檔案 \':name\' 找不到.',
    'menu_label' => '內容',
    'unsaved_label' => '未保存內容',
    'no_list_records' => '找不到內容檔案',
    'delete_confirm_multiple' => '您真的想要刪除選取的檔案或目錄嗎?',
    'delete_confirm_single' => '您真的想要刪除這個內容檔案?',
    'new' => '新內容檔案',
  ],
  'ajax_handler' => [
    'invalid_name' => '不合法的 AJAX 處理器: :name.',
    'not_found' => ' AJAX 處理器 \':name\' 找不到.',
  ],
  'cms' => [
    'menu_label' => 'CMS',
  ],
  'sidebar' => [
    'add' => '增加',
    'search' => '搜尋...',
  ],
  'editor' => [
    'settings' => '設定',
    'title' => '標題',
    'new_title' => '新檔案標題',
    'url' => 'URL',
    'filename' => '檔案名',
    'layout' => '佈局',
    'description' => '描述',
    'preview' => '預覽',
    'meta' => 'Meta',
    'meta_title' => 'Meta 標題',
    'meta_description' => 'Meta 描述',
    'markup' => 'Markup',
    'code' => '代碼',
    'content' => '內容',
    'hidden' => '隱藏',
    'hidden_comment' => '隱藏頁面只能被登錄的後台使用者訪問.',
    'enter_fullscreen' => '進入全螢幕模式',
    'exit_fullscreen' => '退出全螢幕模式',
  ],
  'asset' => [
    'menu_label' => '資源',
    'unsaved_label' => '未保存的資源',
    'drop_down_add_title' => '增加...',
    'drop_down_operation_title' => '動作...',
    'upload_files' => '上傳檔案',
    'create_file' => '新建檔案',
    'create_directory' => '新建目錄',
    'directory_popup_title' => '新目錄',
    'directory_name' => '目錄名',
    'rename' => '重新命名',
    'delete' => '刪除',
    'move' => '移動',
    'select' => '選擇',
    'new' => '新檔案',
    'rename_popup_title' => '重新命名',
    'rename_new_name' => '新名稱',
    'invalid_path' => '路徑名稱只能包含數字, 拉丁字母和以下字元: _-/',
    'error_deleting_file' => '刪除檔案 :name 錯誤.',
    'error_deleting_dir_not_empty' => '刪除目錄 :name 錯誤. 目錄不為空.',
    'error_deleting_dir' => '刪除檔案 :name 錯誤.',
    'invalid_name' => '名稱只能包含數字, 拉丁字母, 空格和以下字元: _-',
    'original_not_found' => '原始檔案或目錄找不到',
    'already_exists' => '檔案或目錄已存在',
    'error_renaming' => '重新命名檔案或目錄錯誤',
    'name_cant_be_empty' => '名稱不能為空',
    'too_large' => '上傳的檔案太大. 最大檔案大小是 :max_size',
    'type_not_allowed' => '只有下面的檔案類型是允許的: :allowed_types',
    'file_not_valid' => '檔案不合法',
    'error_uploading_file' => '上傳檔案錯誤 \':name\': :error',
    'move_please_select' => '請選擇',
    'move_destination' => '目標目錄',
    'move_popup_title' => '移動資源',
    'move_button' => '移動',
    'selected_files_not_found' => '選擇的檔案找不到',
    'select_destination_dir' => '請選擇目標目錄',
    'destination_not_found' => '目標目錄找不到',
    'error_moving_file' => '移動檔案 :file 錯誤',
    'error_moving_directory' => '移動目錄 :dir 錯誤',
    'error_deleting_directory' => '刪除原始目錄 :dir 錯誤',
    'path' => '路徑',
  ],
  'component' => [
    'menu_label' => '組件',
    'invalid_request' => '模板不能保存, 因為非法組件數據.',
    'no_records' => '找不到組件',
    'not_found' => '組件 \':name\' 找不到.',
    'method_not_found' => '組件 \':name\' 不包含方法 \':method\'.',
  ],
  'template' => [
    'invalid_type' => '未知模板類型.',
    'not_found' => '請求模板找不到.',
    'saved' => '模板保存成功.',
  ],
  'permissions' => [
    'name' => 'Cms',
    'manage_content' => '管理內容',
    'manage_assets' => '管理資源',
    'manage_pages' => '管理頁面',
    'manage_layouts' => '管理佈局',
    'manage_partials' => '管理部件',
    'manage_themes' => '管理主題',
  ],
];
