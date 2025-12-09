<?php

namespace Baracod\Larastarterkit\Generator\Backend\Model;

class Relation
{
    public function __construct(
        private string $type,
        private string $relatedModel
    ) {}

    public function getType(): string
    {
        return $this->type;
    }

    public function getRelatedModel(): string
    {
        return $this->relatedModel;
    }

    public static function setDataRelation(string $table, string $module, string $model)
    {

        /*
       -chercher le nom de champs terminant par id
       -configurer les relation belong
            -proposer le nom de de la relation
            -field de la relation seront selection et la correspondance
            -ajouter les clés étrangères


        retour de la fonction :
            - 'type'       => 'belongsTo',
                'foreignKey' => $foreignKey,      // 2e param Eloquent
                'model'      => [
                    'name'      => $modelName,
                    'namespace' => $namespace,
                ],
                'table'      => $table,           // redondant mais conservé si tes consumers l’attendent
                'ownerKey'   => $ownerKey,        // 3e param Eloquent (clé sur le modèle lié)
                'name'       => $methodName,      // nom de la méthode à générer dans le modèle
                'moduleName' =>  $moduleName,
                'externalModule' => $this->moduleName != $moduleName
       */
    }
}
