
const uuidv4 = (): string => {
   return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
      const r = crypto.getRandomValues(new Uint8Array(1))[0] & 15;
      const v = c === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
   });
}

const xorEncrypt = (text: string, key: string) => {
   const textBytes = new TextEncoder().encode(text);
   const keyBytes = new TextEncoder().encode(key);

   const result = new Uint8Array(textBytes.length);
   for (let i = 0; i < textBytes.length; i++) {
      result[i] = textBytes[i] ^ keyBytes[i % keyBytes.length];
   }


   return btoa(String.fromCharCode(...Array.from(result)));
}

const xorDecrypt = (encodedText: string, key: string) => {
   const binaryString = atob(encodedText);
   const textBytes = new Uint8Array(binaryString.length);
   for (let i = 0; i < binaryString.length; i++) {
      textBytes[i] = binaryString.charCodeAt(i);
   }

   const keyBytes = new TextEncoder().encode(key);
   const result = new Uint8Array(textBytes.length);

   for (let i = 0; i < textBytes.length; i++) {
      result[i] = textBytes[i] ^ keyBytes[i % keyBytes.length];
   }

   return new TextDecoder().decode(result);
}


export class rpcClient {
   private static endpoints: string[] = [];
   private static readonly ENDPOINT_INDEX_KEY = 'rpcClient_endpointIndex';
   private static currentEndpointIndex: number = 0;
   private static initialized: boolean = false;
   private static headers: Record<string, string> = { 'Content-Type': 'application/json' };
   private static tokenGetter: (() => string | null) | null = null;
   private static unauthorizedHandler: (() => void) | null = null;
   private static toastHandler: ((message: string) => void) | null = null;
   private static storage: { save: (key: string, value: string) => Promise<void>; get: (key: string) => Promise<string | null> } | null = null;

   public static setStorage(storage: { save: (key: string, value: string) => Promise<void>; get: (key: string) => Promise<string | null> }): void {
      this.storage = storage;
   }

   private static useEncrypt: boolean = false;

   public static setUseEncrypt(useEncrypt: boolean): void {
      this.useEncrypt = useEncrypt;
   }

   public static async init(): Promise<void> {
      if (this.initialized) return;
      if (this.storage) {
         try {
            const savedIndex = await this.storage.get(this.ENDPOINT_INDEX_KEY);
            if (savedIndex !== null) {
               const index = parseInt(savedIndex, 10);
               if (!isNaN(index) && index >= 0 && index < this.endpoints.length) {
                  this.currentEndpointIndex = index;
               }
            }
         } catch (error) {
            console.log('⚠️ Ошибка при загрузке индекса endpoint:', error);
         }
      } else {
         this.currentEndpointIndex = 0;
      }
      this.initialized = true;
   }

   private static async saveEndpointIndex(): Promise<void> {
      if (!this.storage) return;
      try {
         await this.storage.save(this.ENDPOINT_INDEX_KEY, this.currentEndpointIndex.toString());
      } catch (error) {
         console.log('⚠️ Ошибка при сохранении индекса endpoint:', error);
      }
   }

   private static switchToNextEndpoint(): void {
      if (this.endpoints.length <= 1) return;
      this.currentEndpointIndex = (this.currentEndpointIndex + 1) % this.endpoints.length;
      this.saveEndpointIndex();
   }

   private static async waitForEndpoints(): Promise<void> {
      if (this.endpoints.length > 0) return;

      // Ждем 1 секунду и проверяем еще раз
      await new Promise(resolve => setTimeout(resolve, 1000));

      if (this.endpoints.length === 0) {
         throw new Error('Endpoints are not set');
      }
   }

   private static getCurrentEndpoint(): string {
      if (this.endpoints.length === 0) throw new Error('Endpoints are not set');
      return this.endpoints[this.currentEndpointIndex];
   }

   private static isNetworkError(error: any): boolean {
      return (
         error.name === 'AbortError' ||
         error.name === 'TimeoutError' ||
         error.name === 'TypeError' ||
         error.message?.includes('Network request failed') ||
         error.message?.includes('Failed to fetch') ||
         error.message?.includes('NetworkError')
      );
   }

   public static setToken(getter: () => string | null): void {
      this.tokenGetter = getter;
   }

   public static setUnauthorizedHandler(handler: () => void): void {
      this.unauthorizedHandler = handler;
   }

   public static setToast(handler: (message: string) => void): void {
      this.toastHandler = handler;
   }

   public static setEndpoints(urls: string[]): void {
      this.endpoints = urls;
      this.currentEndpointIndex = 0;
      this.initialized = false;
   }

   public static getToken(): string | null {
      if (this.tokenGetter) return this.tokenGetter();
      return null;
   }

   public static setHeaders(headers: Record<string, string>): void {
      this.headers = { ...this.headers, ...headers };
   }

   private static handleError(error: any): void {
      if (error?.type === 'UNAUTHORIZED' || error?.type === 'UNAUTHENTICATED') {
         if (this.unauthorizedHandler) {
            this.unauthorizedHandler();
         }
      }
      if (error?.message) {
         if (this.toastHandler) {
            this.toastHandler(error.message);
         }
      }
   }

   public static async callFormData<R>(method: string, formData: FormData): Promise<R> {
      await this.waitForEndpoints();
      const endpoint = this.getCurrentEndpoint();

      formData.append('method', method);
      formData.append('token', this.getToken());

      const context = this.useEncrypt ? uuidv4() : undefined;

      if (this.useEncrypt) {
         formData.append('context', context);
      }


      const headers = { ...this.headers };
      delete headers['Content-Type'];
      return fetch(endpoint, { method: 'POST', headers, body: formData })
         .then(res => res.json())
         .then((json: RpcResponse<R>) => {

            if (this.useEncrypt && context) {

               if (json.result) {
                  json.result = JSON.parse(xorDecrypt(json.result as string, context));
               }
               if (json.extensions) {
                  json.extensions = JSON.parse(xorDecrypt(json.extensions as string, context));
               }
            }


            if (json?.error) {
               this.handleError(json.error);
               throw json.error;
            }
            this.saveEndpointIndex();
            return json.result as R;
         })
         .catch(err => {
            if (this.isNetworkError(err)) {
               this.switchToNextEndpoint();
            }
            throw err;
         });
   }

   public static async call<P, R, E>(method: string, params?: P, extensions?: Array<string>): Promise<RpcResponse<R, E>> {
      await this.waitForEndpoints();
      const endpoint = this.getCurrentEndpoint();


      const context = this.useEncrypt ? uuidv4() : undefined;

      const body = {
         method, extensions, context: context, token: this.getToken(),
         params: params ? context ? xorEncrypt(JSON.stringify(params ?? {}), context) : params : undefined,
         id: Date.now().toString()
      };

      console.log('🚀🚀🚀 method: ', method, ' RPC Endpoint: ', endpoint);
      console.log('🚀🚀🚀 method: ', method, ' RPC Body: ', JSON.stringify(body));


      return fetch(endpoint, { method: 'POST', headers: this.headers, body: JSON.stringify(body) })
         .then(res => res.json())
         .then((json: RpcResponse<R>) => {

            if (this.useEncrypt && context) {

               if (json.result) {
                  json.result = JSON.parse(xorDecrypt(json.result as string, context));
               }
               if (json.extensions) {
                  json.extensions = JSON.parse(xorDecrypt(json.extensions as string, context));
               }
            }

            console.log('🍪🍪🍪 method: ', method, ' RPC Response: ', JSON.stringify(json));
            if (json?.error) {
               this.handleError(json.error);
               throw json.error;
            }
            this.saveEndpointIndex();

            if (json?.extensions) {
               ExtensionsStore.addToStore(json.extensions);
            }

            return json as RpcResponse<R, E>;
         })
         .catch(err => {
            if (this.isNetworkError(err)) {
               this.switchToNextEndpoint();
            }
            console.log('🛑🛑🛑 RPC Error: ', JSON.stringify(body));
            throw err;
         });
   }
}
