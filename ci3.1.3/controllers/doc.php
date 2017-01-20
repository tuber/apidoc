<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}


class Doc extends CI_Controller
{
    /**
     * Desc: 此方法为定义接口规则,虽然为public ，但并不会在展示列表中。
     * @Date&Time 2017-01-20 10:49
     * User: TongBo
     * @return array
     */
    public  static function getRules() {
        return array(
            'index' => array(
                'userId' => array('name' => 'user_id', 'type' => 'int', 'min' => 1, 'require' => true, 'desc' => '用户ID','default' => 'iamtb.cn'),
            ),

            'service' => array(
                'name' => array('name' => 'name', 'type' => 'string', 'format' => 'explode', 'require' => true, 'desc' => '控制器名字/方法名字','range'=>[100,200],'default'=>'Powered By PhalApi'),

            ),
        );
    }

    //自定义的Controller Path
    private $ctl_path;

    /**
     * 查看文档列表
     * @desc 查看所有控制器/方法列表
     * @return int code 操作码，0表示成功， 1表示用户不存在
     * @return object info 用户信息对象
     * @return int info.id 用户ID
     * @return string info.name 用户名字
     * @return string info.note 用户来源
     * @return string msg 提示信息
     * @return string PostScript 这里只是为了展示，return的数据并不和此接口对等
     * time :2017-01-20 10:42
     */
    public function index()
    {
        $this->ctl_path = APPPATH . 'controllers/';
        $files = $this->listDir($this->ctl_path);

        if (count($files) > 0) {
            foreach ($files as $key => $val) {
                if (strpos($val, '.php')) {
                    include_once $val;
                    // 这里假设的前提为 :Controller文件名===类名,如果不同项目可自定义拼接方式
                    $className = ucfirst(str_replace('.php', '', pathinfo($val)['basename']));
                    //需要严格判断是否父类存在 class_implements
                    $parent_class = get_parent_class($className);

                    //方法名
                    $methodName = get_class_methods($className);

                    if (!empty($parent_class)) {
                        $methodNameByParents = get_class_methods($parent_class);
                    } else {
                        $methodNameByParents = array();
                    }

                    $methodName = array_diff($methodName, $methodNameByParents);

                    foreach ($methodName as $mValue) {
                        $rMethod = new Reflectionmethod($className, $mValue);
                        $title = '//请检测函数注释';
                        $desc = '//请使用@desc 注释';
                        if (!$rMethod->isPublic() || strpos($mValue, '__') === 0 || $mValue === "getRules") {
                            continue;
                        }
                        $docComment = $rMethod->getDocComment();

                        if ($docComment !== false) {
                            $docCommentArr = explode("\n", $docComment);
                            //[0] 一般是/**,这里只要 title desc ，其他的在详情return是返回接口
                            $comment = trim($docCommentArr[1]);
                            $title = trim(substr($comment, strpos($comment, '*') + 1));
                            foreach ($docCommentArr as $comment) {
                                $pos = stripos($comment, '@desc');
                                if ($pos !== false) {
                                    $desc = substr($comment, $pos + 5);
                                }
                            }
                        }
                        $service = $className . '/' . ucfirst($mValue);
                        $allApiS[$service] = array(
                            'service' => $service,
                            'title' => $title,
                            'desc' => $desc,
                        );
                    }
                }
            }
        } else {
            show_error('200', '500', '该目录下 ' . $this->ctl_path . "没有指定文件");
        }
        ksort($allApiS);

        // 按照字母排序，如无此需求可直接return $allApiS
        $list = [];
        foreach ($allApiS as $key => $val) {
            if (!in_array(substr($key, 0, 1), $list)) {

                $list[substr($key, 0, 1)][] = $val;
            } else {
                $list[substr($key, 0, 1)][] = $val;
            }

        }

        $allApiS = $list;
        unset($list);
        //为了简单（粗暴，直接include）,其他模版引擎可自行套套..
        include(APPPATH . "/views/doc/index.html");
    }

    private function listDir($dir)
    {
        $dir .= substr($dir, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR;
        $dirInfo = array();
        foreach (glob($dir . '*') as $v) {
            if (is_dir($v)) {
                $dirInfo = array_merge($dirInfo, listDir($v));
            } else {
                $dirInfo[] = $v;
            }
        }
        return $dirInfo;
    }

    /**
     * 具体文档
     * @desc 控制器/方法
     * @return int code 操作码，0表示成功， 1表示用户不存在
     * @return object info 用户信息对象
     * @return int info.id 用户ID
     * @return string info.name 用户名字
     * @return string info.note 用户来源
     * @return string msg 提示信息
     * @return string PostScript 这里只是为了展示，return的数据并不和此接口对等
     */
    public function service($name = '')
    {

        $get = $this->input->get();
        $path = explode('/', $get['name']);
        $className = $path[0];
        $actionName = strtolower($path[1]);
        $returns = array();
        $description = '';
        $descComment = '//请使用@desc 注释';
        $typeMaps = array(
            'string' => '字符串',
            'int' => '整型',
            'float' => '浮点型',
            'boolean' => '布尔型',
            'date' => '日期',
            'array' => '数组',
            'fixed' => '固定值',
            'enum' => '枚举类型',
            'object' => '对象',
        );

        require_once($this->ctl_path . $className . ".php");

        if (method_exists($className, 'getRules')) {
            //此为类下定义的所有rules，以action name为下标
            $rules_all = $className::getRules();
        } else {
            $rules_all = [];
        }

        if (array_key_exists($actionName, $rules_all)) {
            $rules = $rules_all[$actionName];
        } else {
            $rules = [];
        }

        $service=$className."/".$actionName;
        $rMethod = new ReflectionMethod($className, $actionName);
        $docComment = $rMethod->getDocComment();
        $docCommentArr = explode("\n", $docComment);
        foreach ($docCommentArr as $comment) {
            $comment = trim($comment);
            //标题描述
            if (empty($description) && strpos($comment, '@') === false && strpos($comment, '/') === false) {
                $description = substr($comment, strpos($comment, '*') + 1);
                continue;
            }
            //@desc注释
            $pos = stripos($comment, '@desc');
            if ($pos !== false) {
                $descComment = substr($comment, $pos + 5);
                continue;
            }
            //@return注释
            $pos = stripos($comment, '@return');
            if ($pos === false) {
                continue;
            }
            $returnCommentArr = explode(' ', substr($comment, $pos + 8));
            //将数组中的空值过滤掉，同时将需要展示的值返回
            $returnCommentArr = array_values(array_filter($returnCommentArr));
            if (count($returnCommentArr) < 2) {
                continue;
            }
            if (!isset($returnCommentArr[2])) {
                $returnCommentArr[2] = '';    //可选的字段说明
            } else {
                //兼容处理有空格的注释
                $returnCommentArr[2] = implode(' ', array_slice($returnCommentArr, 2));
            }

            $returns[] = $returnCommentArr;
        }
        //是的，这里一个模版是html，一个是php。二选一..
        include(APPPATH . "views/doc/service.php");
    }

}
