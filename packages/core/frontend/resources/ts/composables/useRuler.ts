import { useI18n } from 'vue-i18n'

/** Signature attendue par Vuetify */
export type RuleFn = (v: unknown) => true | string

/* ------------------------------------------------------------------ */
/*  REGEX “standard”                                                  */
/* ------------------------------------------------------------------ */
const REGEX = {
  // eslint-disable-next-line regexp/no-obscure-range
  alpha: /^[A-Za-zÀ-ÖØ-öø-ÿ]+$/,
  alphaDash: /^[\w-]+$/,
  alphaNum: /^[A-Z0-9]+$/i,
  email: /^[^\s@]+@[^\s@][^\s.@]*\.[^\s@]+$/,
  ip: /^(25[0-5]|2[0-4]\d|1\d{2}|[1-9]?\d)(\.(\1)){3}$/,
  ipv4: /^(25[0-5]|2[0-4]\d|1\d{2}|[1-9]?\d)(\.(\1)){3}$/,
  ipv6: /^(([0-9a-f]{1,4}:){7}[0-9a-f]{1,4}|::1)$/i,
  uuid: /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
  url: /^(https?|ftp):\/\/[^\s/$.?#].\S*$/i,
  json: /^[[{].*[\]}]$/s,
}

/* ------------------------------------------------------------------ */
/*  COMPOSABLE                                                        */
/* ------------------------------------------------------------------ */
export function useValidation() {
  const { t } = useI18n()

  const m = (key: string, params: Record<string, unknown> = {}) =>
    t(`message.validation.${key}`, params).replace(':field', String(params.field))

  /* ---------------- RÈGLES GÉNÉRIQUES ---------------- */
  const required = (label: string): RuleFn => v =>
    (!!v || v === 0) || m('required', { field: label })

  const boolean = (label: string): RuleFn => v =>
    typeof v === 'boolean' || m('boolean', { field: label })

  const array = (label: string): RuleFn => v =>
    Array.isArray(v) || m('array', { field: label })

  const string = (label: string): RuleFn => v =>
    typeof v === 'string' || m('string', { field: label })

  const numeric = (label: string): RuleFn => v =>
    !Number.isNaN(Number(v)) || m('numeric', { field: label })

  const integer = (label: string): RuleFn => (v: any) =>
    Number.isInteger(+v) || m('integer', { field: label })

  const alpha = (label: string): RuleFn => v =>
    (typeof v === 'string' && REGEX.alpha.test(v)) || m('alpha', { field: label })

  const alphaDash = (label: string): RuleFn => v =>
    (typeof v === 'string' && REGEX.alphaDash.test(v))
    || m('alpha_dash', { field: label })

  const alphaNum = (label: string): RuleFn => v =>
    (typeof v === 'string' && REGEX.alphaNum.test(v))
    || m('alpha_num', { field: label })

  const min = (label: string, minVal: number): RuleFn => (v: any) =>
    (typeof v === 'string' ? v.length >= minVal : +v >= minVal)
    || m('min', { field: label, min: minVal })

  const max = (label: string, maxVal: number): RuleFn => (v: any) =>
    (typeof v === 'string' ? v.length <= maxVal : +v <= maxVal)
    || m('max', { field: label, max: maxVal })

  const between = (label: string, minVal: number, maxVal: number): RuleFn => (v: any) =>
    (typeof v === 'string'
      ? v.length >= minVal && v.length <= maxVal
      : +v >= minVal && +v <= maxVal)
    || m('digits_between', { field: label, min: minVal, max: maxVal })

  /* ---------------- TYPES SPÉCIFIQUES ---------------- */
  const email = (label: string): RuleFn => v =>
    (typeof v === 'string' && REGEX.email.test(v))
    || m('email', { field: label })

  const uuid = (label: string): RuleFn => v =>
    (typeof v === 'string' && REGEX.uuid.test(v))
    || m('uuid', { field: label })

  const ip = (label: string): RuleFn => v =>
    (typeof v === 'string' && REGEX.ip.test(v)) || m('ip', { field: label })

  const ipv4 = (label: string): RuleFn => v =>
    (typeof v === 'string' && REGEX.ipv4.test(v)) || m('ipv4', { field: label })

  const ipv6 = (label: string): RuleFn => v =>
    (typeof v === 'string' && REGEX.ipv6.test(v)) || m('ipv6', { field: label })

  const url = (label: string): RuleFn => v =>
    (typeof v === 'string' && REGEX.url.test(v)) || m('url', { field: label })

  const json = (label: string): RuleFn => v =>
    (typeof v === 'string' && REGEX.json.test(v)) || m('json', { field: label })

  /* ---------------- DATE ---------------- */
  const date = (label: string): RuleFn => v =>
    !Number.isNaN(Date.parse(String(v))) || m('date', { field: label })

  const after = (label: string, ref: string | Date): RuleFn => v =>
    Date.parse(String(v)) > Date.parse(String(ref))
    || m('after', { field: label, date: ref })

  const afterOrEqual = (label: string, ref: string | Date): RuleFn => v =>
    Date.parse(String(v)) >= Date.parse(String(ref))
    || m('after_or_equal', { field: label, date: ref })

  const dateEquals = (label: string, ref: string | Date): RuleFn => v =>
    Date.parse(String(v)) === Date.parse(String(ref))
    || m('date_equals', { field: label, date: ref })

  /* ---------------- CORRESPONDANCE ---------------- */
  const same = (
    label: string,
    otherLabel: string,
    getOther: () => unknown,
  ): RuleFn => v =>
    v === getOther() || m('same', { field: label, other: otherLabel })

  const confirmed = (
    label: string,
    getOriginal: () => unknown,
  ): RuleFn => v =>
    v === getOriginal() || m('confirmed', { field: label })

  /* ---------------- EXPORT ---------------- */
  return {
    /* génériques */
    required,
    boolean,
    array,
    string,
    numeric,
    integer,
    alpha,
    alphaDash,
    alphaNum,
    min,
    max,
    between,

    /* types particuliers */
    email,
    uuid,
    ip,
    ipv4,
    ipv6,
    url,
    json,

    /* date */
    date,
    after,
    afterOrEqual,
    dateEquals,

    /* correspondance */
    same,
    confirmed,
  }
}
