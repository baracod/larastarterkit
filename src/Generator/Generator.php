<?php

namespace Baracod\Larastarterkit\Generator;

class Generator
{
    private ?string $moduleName;

    private ?string $tableName;

    private ?string $modelName;

    private ?ModuleGenerator $module;

    private ?ModelGenerator $model;

    private ?ControllerGenerator $controller;

    private ?RequestGenerator $request;

    private ?TypeScriptGenerator $frontend;

    /*

     -FRONTEND :
        - Type
        - Api
        - lang
        - AddOrEditComponent
        - ReadComponent
        - Index (page): la liste
     -BACKEND :
        - Model
        - Request
        - Controller
        - Api path:route

    */

    /**
     * Constructeur de la classe Generator.
     * Initialise le générateur de module. Le module est créé s'il n'existe pas.
     *
     * @param  string  $name  Le nom du module.
     */
    public function __construct(string $moduleName, string $tableName, string $modelName)
    {
        $this->moduleName = $moduleName;
        // L'instanciation de ModuleGenerator déclenche sa création s'il n'existe pas.
        $this->tableName = $tableName;
        $this->module = new ModuleGenerator($moduleName);
        $this->model = new ModelGenerator($modelName, $tableName, $moduleName);
        $this->controller = new ControllerGenerator($tableName, $modelName, $moduleName, $moduleName);
        $this->request = new RequestGenerator($moduleName, $tableName, $moduleName);
        $this->frontend = new TypeScriptGenerator($moduleName, $tableName, $modelName);
    }

    // -BACKEND :
    //     - Model
    //     - Request
    //     - Controller
    //     - Api path

    public function generateModel(string $modelName = '')
    {
        if ($modelName) {
            $this->model->setModelName($modelName);
        }

        return $this->model->generate();
    }
}
