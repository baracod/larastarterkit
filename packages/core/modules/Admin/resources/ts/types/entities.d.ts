export interface IAdminSetting {
      type: any;
  module?: string | null;
  user_id?: number | null;
  key?: string | null;
  value?: string | null;
  value_type: string;
  label?: string | null;
  description?: string | null;
  input_type: string;
  options?: string | null;
  default_value?: string | null;
  is_public: number;
}
