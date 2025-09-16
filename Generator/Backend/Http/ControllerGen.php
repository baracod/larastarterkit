<?php

namespace App\Generator\Backend\Http;

use RuntimeException;
use App\Generator\ModuleGenerator;
use Illuminate\Support\Facades\File;
use App\Generator\Backend\Model\ModelGen;

use function Laravel\Prompts\note;

class ControllerGen
{
    private $modelGen = null;
    private $modelName = null;
    private $modelFqcn = null;
    private $modelKey = null;
    private $moduleName = null;
    private $controllerFilePath = null;
    private $controllerDirectoryPath = null;
    private $controllerStubPath = null;
    private $controllerNamespace = null;
    private $controllerName = null;
    private ?ModuleGenerator $moduleGen = null;

    public function __construct(ModelGen $modelGen)
    {
        $this->modelGen = $modelGen;
        $this->modelName = $modelGen->getModelName();
        $this->modelKey = $modelGen->getModelKey();
        $this->moduleName = $modelGen->getModuleName();
        $this->modelFqcn = $modelGen->getFqcn();

        $this->moduleGen = new ModuleGenerator($this->moduleName);
        $this->controllerNamespace = $this->moduleGen->getControllerNamespace();
        $this->controllerDirectoryPath = $this->moduleGen->getPathControllers();
        $this->controllerName = $this->modelName . 'Controller';
        $this->controllerFilePath = $this->moduleGen->getPathControllers() . '/' . $this->controllerName . '.php';
        $this->controllerStubPath = base_path('app/Generator/Backend/Stubs/backend/Controller.stub');
    }

    public function generate()
    {
        $replacements = [
            '{{ controllerNamespace }}'       => $this->controllerNamespace,
            '{{ controllerName }}'       => $this->controllerName,
            '{{ modelFqcn }}'       => $this->modelFqcn,
            '{{ modelName }}'        => $this->modelName,
            '{{ requestFqcn }}'       => 'Illuminate\Http\Request',
            '{{ requestName }}'         => 'Request',
            '{{ requestNamespace }}'      => '',
        ];

        if (!File::exists($this->controllerStubPath)) {
            throw new RuntimeException("Stub introuvable: {$this->controllerStubPath}");
        }
        $template = File::get($this->controllerStubPath);
        $content = strtr($template, $replacements);

        File::ensureDirectoryExists($this->controllerDirectoryPath, 0755);
        File::put($this->controllerFilePath, $content);
    }
}
