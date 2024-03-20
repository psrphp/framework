<?php

declare(strict_types=1);

namespace PsrPHP\Framework;

use Exception;
use SplPriorityQueue;

class Template
{
    protected $path_list = [];
    protected $extends = [];

    protected $literals = [];
    protected $code = '';
    protected $data = [];
    protected $filename = '';

    public function __construct()
    {
        foreach (Framework::getAppList() as $appname => $appdir) {
            $this->addPath($appname, $appdir . '/src/template');
        }
        $this->assign([
            'db' => Framework::getDb(),
            'cache' => Framework::getCache(),
            'logger' => Framework::getLogger(),
            'router' => Framework::getRouter(),
            'config' => Framework::getConfig(),
            'session' => Framework::getSession(),
            'request' => Framework::getRequest(),
            'template' => $this,
            'container' => Framework::getContainer(),
        ]);
        $this->extend('/\{cache\s*(.*)\s*\}([\s\S]*)\{\/cache\}/Ui', function ($matchs) {
            $params = array_filter(explode(',', trim($matchs[1])));
            if (!isset($params[0])) {
                $params[0] = 3600;
            }
            if (!isset($params[1])) {
                $params[1] = 'tpl_extend_cache_' . md5($matchs[2]);
            }
            return '<?php echo call_user_func(function($args){
                extract($args);
                if (!$cache->has(\'' . $params[1] . '\')) {
                    $res = $template->renderFromString(base64_decode(\'' . base64_encode($matchs[2]) . '\'), $args, \'__' . $params[1] . '\');
                    $cache->set(\'' . $params[1] . '\', $res, ' . $params[0] . ');
                }else{
                    $res = $cache->get(\'' . $params[1] . '\');
                }
                return $res;
            }, get_defined_vars());?>';
        });
    }

    public function addPath(string $name, string $path, $priority = 0): self
    {
        if (!isset($this->path_list[$name])) {
            $this->path_list[$name] = new SplPriorityQueue;
        }
        $this->path_list[$name]->insert($path, $priority);
        return $this;
    }

    public function extend(string $preg, callable $callback): self
    {
        $this->extends[$preg] = $callback;
        return $this;
    }

    public function assign($name, $value = null): self
    {
        if (is_array($name)) {
            $this->data = array_merge($this->data, $name);
        } else {
            $this->data[$name] = $value;
        }
        return $this;
    }

    public function renderFromFile(string $file, array $data = []): string
    {
        if ($data) {
            $this->assign($data);
        }

        $cache_key = $this->getCacheKey($file);

        if (Framework::getCache()->has($cache_key)) {
            $code = Framework::getCache()->get($cache_key);
        } else {
            $code = $this->parseString($this->getTplFileContent($file));
            Framework::getCache()->set($cache_key, $code);
        }

        $this->code = $code;
        $this->filename = $file;
        return $this->render();
    }

    public function renderFromString(string $string, array $data = [], string $filename = ''): string
    {
        if ($data) {
            $this->assign($data);
        }

        $cache_key = $this->getCacheKey($filename ?: md5($string));

        if (Framework::getCache()->has($cache_key)) {
            $code = Framework::getCache()->get($cache_key);
        } else {
            $code = $this->parseString($string);
            Framework::getCache()->set($cache_key, $code);
        }

        $this->code = $code;
        $this->filename = $filename;
        return $this->render();
    }

    private function getTplFile(string $tpl): ?string
    {
        list($file, $name) = explode('@', $tpl);
        if ($name && $file && isset($this->path_list[$name])) {
            foreach (clone $this->path_list[$name] as $path) {
                $fullname = $path . DIRECTORY_SEPARATOR . $file . '.php';
                if (is_file($fullname)) {
                    return $fullname;
                }
            }
        }
        return null;
    }

    private function getCacheKey(string $name): string
    {
        return str_replace(['{', '}', '(', ')', '/', '\\', '@', ':'], '_', 'tpl_' . $name);
    }

    private function parseString(string $string): string
    {
        $string = $this->buildLiteral($string);
        $string = $this->parseTag($string);
        $string = $this->parseLiteral($string);
        return $string;
    }

    private function getTplFileContent(string $tpl): string
    {
        if ($filename = $this->getTplFile($tpl)) {
            return file_get_contents($filename);
        }
        throw new Exception('template [' . $tpl . '] is not found!');
    }

    private function buildLiteral(string $html): string
    {
        return preg_replace_callback(
            '/{literal}([\s\S]*){\/literal}/Ui',
            function ($matchs) {
                $key = '#' . md5($matchs[1]) . '#';
                $this->literals[$key] = $matchs[1];
                return $key;
            },
            $html
        );
    }

    private function parseLiteral(string $html): string
    {
        return str_replace(
            array_keys($this->literals),
            array_values($this->literals),
            $html
        );
    }

    private function parseTag(string $html): string
    {
        $tags = [
            '/\{(foreach|if|for|switch|while)\s+(.*)\}/Ui' => function ($matchs) {
                return '<?php ' . $matchs[1] . ' (' . $matchs[2] . ') { ?>';
            },
            '/\{function\s+(.*)\}/Ui' => function ($matchs) {
                return '<?php function ' . $matchs[1] . '{ ?>';
            },
            '/\{php\s+(.*)\s*;?\s*\}/Ui' => function ($matchs) {
                return '<?php ' . $matchs[1] . '; ?>';
            },
            '/\{dump\s+(.*)\s*;?\s*\}/Ui' => function ($matchs) {
                return '<pre><?php ob_start();var_dump(' . $matchs[1] . ');echo htmlspecialchars(ob_get_clean()); ?></pre>';
            },
            '/\{print\s+(.*)\s*;?\s*\}/Ui' => function ($matchs) {
                return '<pre><?php echo htmlspecialchars(print_r(' . $matchs[1] . ', true)); ?></pre>';
            },
            '/\{echo\s+(.*)\s*;?\s*\}/Ui' => function ($matchs) {
                return '<?php echo ' . $matchs[1] . '; ?>';
            },
            '/\{case\s+(.*)\}/Ui' => function ($matchs) {
                return '<?php case ' . $matchs[1] . ': ?>';
            },
            '/\{default\s*\}/Ui' => function ($matchs) {
                return '<?php default: ?>';
            },
            '/\{php\}/Ui' => function ($matchs) {
                return '<?php ';
            },
            '/\{\/php\}/Ui' => function ($matchs) {
                return ' ?>';
            },
            '/\{\/(foreach|if|for|function|switch|while)\}/Ui' => function ($matchs) {
                return '<?php } ?>';
            },
            '/\{\/(case|default)\}/Ui' => function ($matchs) {
                return '<?php break; ?>';
            },
            '/\{(elseif)\s+(.*)\}/Ui' => function ($matchs) {
                return '<?php }' . $matchs[1] . '(' . $matchs[2] . '){ ?>';
            },
            '/\{else\/?\}/Ui' => function ($matchs) {
                return '<?php }else{ ?>';
            },
            '/\{include\s*([\w\-_\.,@\/]*)\}/Ui' => function ($matchs) {
                $html = '';
                $tpls = explode(',', $matchs[1]);
                foreach ($tpls as $tpl) {
                    $html .= $this->getTplFileContent($tpl);
                }
                return $this->parseTag($this->buildLiteral($html));
            },
            '/\{(\$[^{}\'"]*)((\.[^{}\'"]+)+)\}/Ui' => function ($matchs) {
                return '<?php echo htmlspecialchars(' . $matchs[1] . substr(str_replace('.', '\'][\'', $matchs[2]), 2) . '\']' . '); ?>';
            },
            '/\{(\$[^{}]*)\}/Ui' => function ($matchs) {
                return '<?php echo htmlspecialchars(' . $matchs[1] . '); ?>';
            },
            '/\{:([^{}]*)\s*;?\s*\}/Ui' => function ($matchs) {
                return '<?php echo htmlspecialchars(' . $matchs[1] . '); ?>';
            },
            '/\?>[\s]*<\?php/is' => function ($matchs) {
                return '';
            },
        ];
        $tags = array_merge($tags, $this->extends);
        foreach ($tags as $preg => $callback) {
            $html = preg_replace_callback($preg, $callback, $html);
        }
        return $html;
    }

    private function render(): string
    {
        if (!strlen($this->code)) {
            return '';
        }
        ob_start();
        extract($this->data);
        $____file____ = tempnam(sys_get_temp_dir(), 'tpl_' . $this->filename) . '.php';
        file_put_contents($____file____, $this->code);
        include $____file____;
        @unlink($____file____);
        return ob_get_clean();
    }
}
