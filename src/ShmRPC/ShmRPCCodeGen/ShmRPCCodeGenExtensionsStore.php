<?php

namespace Shm\ShmRPC\ShmRPCCodeGen;

use Shm\ShmTypes\BaseType;

class ShmRPCCodeGenExtensionsStore
{

    private static array $extensions = [];

    public static function addExtension(string $key, BaseType $extension)
    {
        self::$extensions[$key] = $extension;
    }


    public static function tsClass(): string
    {




        $storeType = [];

        $storeInputType = [];

        $addToStoreFunctionCode = [];

        $defaultStore = [];

        $getByIDFunctionsCode = [];
        $getByIDsFunctionsCode = [];

        foreach (self::$extensions as $key => $extension) {
            $storeType[] = $key . ':  {[key:string]: ' . $extension->tsType()->getTsTypeName() . '}';
            $storeInputType[] = $key . '?:  ' . $extension->tsType()->getTsTypeName() . '[]';

            $defaultStore[] = $key . ': {}';


            $getByIDFunctionsCode[] = 'public static ' . $key . 'ByID(id?: string | null): ' . $extension->tsType()->getTsTypeName() . ' | null {
            
            
            if(!id){
                return null;
            }

            if(this.store.' . $key . '?.[id]){
                return this.store.' . $key . '[id];
            }

            return null;
            }';

            $getByIDsFunctionsCode[] = 'public static ' . $key . 'ByIDs(ids: string[]): ' . $extension->tsType()->getTsTypeName() . '[] {
            
            const result = [];
            for(const id of ids){
                if(this.store.' . $key . '[id]){
                    result.push(this.store.' . $key . '[id]);
                }
            }
            return result;

            }';

            $addToStoreFunctionCode[] = 'if(value?.' . $key . ' && value?.' . $key . '.length > 0) {
            
            for(const item of value?.' . $key . ') {
                 if(item?._id){
                     this.store.' . $key . '[item._id] = item;
                 }
            }

            }';
        }


        $addToStoreFunctionCode = implode('\n', $addToStoreFunctionCode);

        $defaultStore = implode(', ', $defaultStore);

        $getByIDFunctionsCode = implode('\n', $getByIDFunctionsCode);

        $getByIDsFunctionsCode = implode('\n', $getByIDsFunctionsCode);

        $code = '
        
        
        type ExtensionsStoreType = {' . implode(', ', $storeType) . '};
        
        type ExtensionsStoreInputType = {' . implode(', ', $storeInputType) . '};
        
        export class ExtensionsStore {
        

        private static store: ExtensionsStoreType = {' . $defaultStore . '};
        
        public static addToStore(value: ExtensionsStoreInputType) {
        
        ' . $addToStoreFunctionCode . '


        }

        ' . $getByIDFunctionsCode . '


        ' . $getByIDsFunctionsCode . '
        
          }';

        return $code;
    }
}
