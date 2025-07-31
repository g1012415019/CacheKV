<?php

/**
 * CacheKV 文档连贯性检查脚本
 * 
 * 检查 README 和 docs 文档之间的连贯性
 */

class DocumentationChecker
{
    private $basePath;
    private $issues = [];
    
    public function __construct($basePath)
    {
        $this->basePath = $basePath;
    }
    
    public function checkAll()
    {
        echo "🔍 开始检查 CacheKV 文档连贯性...\n\n";
        
        $this->checkReadmeLinks();
        $this->checkDocumentConsistency();
        $this->checkCodeExamples();
        
        $this->reportResults();
    }
    
    private function checkReadmeLinks()
    {
        echo "📋 检查 README 文档链接...\n";
        
        $readmePath = $this->basePath . '/README.md';
        $readmeContent = file_get_contents($readmePath);
        
        // 提取所有文档链接
        preg_match_all('/\[([^\]]+)\]\(docs\/([^)]+)\)/', $readmeContent, $matches);
        
        $checkedFiles = [];
        for ($i = 0; $i < count($matches[0]); $i++) {
            $linkText = $matches[1][$i];
            $filePath = $matches[2][$i];
            $fullPath = $this->basePath . '/docs/' . $filePath;
            
            if (!in_array($filePath, $checkedFiles)) {
                $checkedFiles[] = $filePath;
                
                if (file_exists($fullPath)) {
                    echo "  ✅ {$filePath} - 存在\n";
                } else {
                    echo "  ❌ {$filePath} - 不存在\n";
                    $this->issues[] = "文档文件不存在: docs/{$filePath}";
                }
            }
        }
        
        echo "\n";
    }
    
    private function checkDocumentConsistency()
    {
        echo "🔄 检查文档内容一致性...\n";
        
        // 检查核心概念是否一致
        $this->checkCoreConceptConsistency();
        
        // 检查代码示例是否一致
        $this->checkCodeExampleConsistency();
        
        // 检查功能描述准确性
        $this->checkFunctionalityAccuracy();
        
        echo "\n";
    }
    
    private function checkCoreConceptConsistency()
    {
        $readmeContent = file_get_contents($this->basePath . '/README.md');
        $gettingStartedContent = file_get_contents($this->basePath . '/docs/getting-started.md');
        
        // 检查核心描述是否一致
        $coreDescription = '核心功能是实现"若无则从数据源获取并回填缓存"这一常见模式';
        
        if (strpos($readmeContent, $coreDescription) !== false) {
            echo "  ✅ README 包含核心描述\n";
        } else {
            echo "  ❌ README 缺少核心描述\n";
            $this->issues[] = "README 缺少核心描述";
        }
        
        if (strpos($gettingStartedContent, $coreDescription) !== false) {
            echo "  ✅ 入门指南包含核心描述\n";
        } else {
            echo "  ❌ 入门指南缺少核心描述\n";
            $this->issues[] = "入门指南缺少核心描述";
        }
        
        // 检查三大核心功能是否一致
        $coreFunctions = [
            '自动回填缓存',
            '批量数据操作',
            '基于标签的缓存失效管理'
        ];
        
        foreach ($coreFunctions as $function) {
            if (strpos($readmeContent, $function) !== false && 
                strpos($gettingStartedContent, $function) !== false) {
                echo "  ✅ 核心功能 '{$function}' 在两个文档中都存在\n";
            } else {
                echo "  ❌ 核心功能 '{$function}' 在文档中不一致\n";
                $this->issues[] = "核心功能 '{$function}' 在文档中不一致";
            }
        }
    }
    
    private function checkCodeExampleConsistency()
    {
        echo "  📝 检查代码示例一致性...\n";
        
        $readmeContent = file_get_contents($this->basePath . '/README.md');
        $gettingStartedContent = file_get_contents($this->basePath . '/docs/getting-started.md');
        
        // 检查关键代码示例
        $keyExamples = [
            'getUserFromDatabase(123)',
            'new ArrayDriver()',
            'CacheKVServiceProvider::register'
        ];
        
        foreach ($keyExamples as $example) {
            $inReadme = strpos($readmeContent, $example) !== false;
            $inGettingStarted = strpos($gettingStartedContent, $example) !== false;
            
            if ($inReadme && $inGettingStarted) {
                echo "    ✅ 代码示例 '{$example}' 在两个文档中都存在\n";
            } else {
                echo "    ⚠️  代码示例 '{$example}' 在文档中不一致\n";
                $this->issues[] = "代码示例 '{$example}' 在文档中不一致";
            }
        }
    }
    
    private function checkCodeExamples()
    {
        echo "🧪 检查代码示例可执行性...\n";
        
        // 检查 example.php 是否可以运行
        $examplePath = $this->basePath . '/example.php';
        if (file_exists($examplePath)) {
            echo "  ✅ example.php 存在\n";
            
            // 尝试语法检查
            $output = [];
            $returnCode = 0;
            exec("php -l {$examplePath} 2>&1", $output, $returnCode);
            
            if ($returnCode === 0) {
                echo "  ✅ example.php 语法正确\n";
            } else {
                echo "  ❌ example.php 语法错误\n";
                $this->issues[] = "example.php 语法错误: " . implode("\n", $output);
            }
        } else {
            echo "  ❌ example.php 不存在\n";
            $this->issues[] = "example.php 文件不存在";
        }
        
        echo "\n";
    }
    
    private function reportResults()
    {
        echo "📊 检查结果报告\n";
        echo str_repeat("=", 50) . "\n";
        
        if (empty($this->issues)) {
            echo "🎉 恭喜！所有文档检查都通过了！\n";
            echo "✅ README 和 docs 文档完全连贯\n";
            echo "✅ 核心概念描述一致\n";
            echo "✅ 代码示例一致\n";
            echo "✅ 文档链接有效\n";
        } else {
            echo "⚠️  发现 " . count($this->issues) . " 个问题需要修复：\n\n";
            
            foreach ($this->issues as $index => $issue) {
                echo ($index + 1) . ". {$issue}\n";
            }
            
            echo "\n💡 建议：\n";
            echo "- 确保 README 和入门指南的核心描述保持一致\n";
            echo "- 检查所有文档链接是否指向正确的文件\n";
            echo "- 保持代码示例在不同文档中的一致性\n";
            echo "- 定期运行此脚本检查文档连贯性\n";
        }
        
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "检查完成！\n";
    }
    
    private function checkFunctionalityAccuracy()
    {
        echo "  🔍 检查功能描述准确性...\n";
        
        // 检查是否还有 DataCache 引用
        $files = glob($this->basePath . '/docs/*.md');
        $dataCacheFound = false;
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (strpos($content, 'DataCache') !== false) {
                $dataCacheFound = true;
                $filename = basename($file);
                echo "    ❌ {$filename} 中仍有 DataCache 引用\n";
                $this->issues[] = "{$filename} 中仍有 DataCache 引用";
            }
        }
        
        if (!$dataCacheFound) {
            echo "    ✅ 所有文档已正确使用 CacheKV 命名\n";
        }
        
        // 检查命名空间是否正确
        $wrongNamespaceFound = false;
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (strpos($content, 'Asfop\\DataCache') !== false) {
                $wrongNamespaceFound = true;
                $filename = basename($file);
                echo "    ❌ {$filename} 中有错误的命名空间引用\n";
                $this->issues[] = "{$filename} 中有错误的命名空间引用";
            }
        }
        
        if (!$wrongNamespaceFound) {
            echo "    ✅ 所有文档使用正确的命名空间\n";
        }
        
        // 检查源代码中的注释是否正确
        $sourceFiles = glob($this->basePath . '/src/**/*.php');
        $sourceIssues = false;
        
        foreach ($sourceFiles as $file) {
            $content = file_get_contents($file);
            if (strpos($content, 'DataCache 实例') !== false) {
                $sourceIssues = true;
                $filename = str_replace($this->basePath . '/', '', $file);
                echo "    ❌ {$filename} 中有错误的类名引用\n";
                $this->issues[] = "{$filename} 中有错误的类名引用";
            }
        }
        
        if (!$sourceIssues) {
            echo "    ✅ 源代码注释使用正确的类名\n";
        }
    }
}

// 运行检查
$checker = new DocumentationChecker(__DIR__);
$checker->checkAll();
