<?php
/*
 * Copyright (c) 2023.  ZAP.CN  - ZAP CMS
 */

namespace zapcms\services;

use zap\traits\SingletonTrait;

class Editor
{
    use SingletonTrait;

    protected $options = [
        'height'=>400,
        'lang'=>'zh-CN',
        'upload_url'=>'ZAP_UPLOAD_URL',
        'image_upload'=>'function(files){}',
        'on_change'=>'function(){}',
    ];

    protected function init()
    {
        \zapcms\support\Asset::library('summernote');
        register_scripts(base_url('/assets/plugins/zapuploader.js'));
    }

    public function setOptions($options = []){
        $this->options = array_merge($this->options,$options);
        $this->register();
        return $this;
    }
    
    public function create(string $name,$options = []){
        $this->setOptions($options);
        register_scripts(<<<EOF
function createEditor(){
$('{$name}').summernote({
    imageAttributes: {
        icon: '<i class="note-icon-pencil"/>',
        figureClass: 'figureClass',
        figcaptionClass: 'captionClass',
        captionText: 'Caption Goes Here.',
        manageAspectRatio: true 
    },
    popover: {
        image: [
            ['imagesize', ['imageSize100', 'imageSize50', 'imageSize25']],
            ['float', ['floatLeft', 'floatRight', 'floatNone']],
            ['remove', ['removeMedia']],
            ['custom', ['imageAttributes']],
        ],
    },
    height: {$this->options['height']},
    lang:'{$this->options['lang']}',
    toolbar: [
    ['font', ['bold', 'italic', 'underline', 'clear']],
    ['color', ['color']],
    ['para', ['ul', 'ol', 'paragraph']],
    ['insert', ['link', 'picture', 'table', 'hr']],
    ['view', ['fullscreen', 'codeview']],
    ['snfinder',['snfinder']]
  ],
    callbacks:{
        onImageUpload: {$this->options['image_upload']},
        onChange: {$this->options['on_change']}
    }
});
}
createEditor();
EOF,ASSETS_BODY_TEXT
);
    }

    protected function register(){
        register_scripts(<<<EOF
function zapSendFiles(files) {
    var \$this = $(this);
    for (const i in files) {
         zapUploadFile(files[i],function(url){
            \$this.summernote("insertImage", url);    
        });   
    }
    
}
function zapSendFile(files) {
    var \$this = $(this);   
    console.log(\$this);
    zapUploadFile(files[0],function(url){
        \$this.summernote("insertImage", url);    
    });    
    
    
}
function zapUploadFile(file,callback) {
  data = new FormData();
  data.append("file", file);
  $.ajax({
      data: data,
      type: "POST",
      url: "{$this->options['upload_url']}",
      cache: false,
      contentType: false,
      processData: false,
      dataType:'json',
      success: function(data) {
        if(data.code == 0){
            callback(data.url);
        }else{
            ZapToast.alert(data.msg, {bgColor: bgDanger, position: Toast_Pos_Center});
        }
      }
  });
}
EOF,ASSETS_BODY_TEXT
        );
    }




}