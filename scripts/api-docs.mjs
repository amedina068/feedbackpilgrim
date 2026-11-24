// Makes the generated API files from docs/api/openapi.yaml:
//
// - public/docs/openapi.json: the spec that the app serves and renders
// - docs/api/postman_collection.json
// - docs/api/postman_environment.json
//
// Run it with "composer api-docs". The tools run with npx at pinned versions,
// so they are not dependencies of the project.
import { execFileSync } from 'node:child_process'
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const JS_YAML = 'js-yaml@5.5.0'
const OPENAPI_TO_POSTMAN = 'openapi-to-postmanv2@6.4.2'
const WIDGET_INIT_PATH = 'api/v1/init'

const placeholders = {
  baseUrl: 'https://app.example.com',
  token: 'YOUR_API_TOKEN',
  publishableKey: 'pk_YOUR_PUBLISHABLE_KEY',
}

const bearer = (variable) => ({ type: 'bearer', bearer: [{ key: 'token', value: `{{${variable}}}`, type: 'string' }] })

// The converter gives each item a new random id on each run. Postman does
// not need the ids, so the collection drops them.
function withoutRandomIds(value) {
  if (Array.isArray(value)) {
    return value.map(withoutRandomIds)
  }

  if (value === null || typeof value !== 'object') {
    return value
  }

  return Object.fromEntries(Object.entries(value)
    .filter(([key, child]) => key !== 'id' && key !== '_postman_id' && !(key === 'auth' && child === null))
    .map(([key, child]) => [key, withoutRandomIds(child)]))
}

function withWidgetKey(item) {
  if (item.item) {
    return { ...item, item: item.item.map(withWidgetKey) }
  }

  if (item.request.url.path.join('/') !== WIDGET_INIT_PATH) {
    return item
  }

  return { ...item, request: { ...item.request, auth: bearer('publishableKey') } }
}

export function postmanCollection(converted) {
  const collection = withoutRandomIds(converted)

  return {
    ...collection,
    auth: bearer('token'),
    variable: [{ key: 'baseUrl', value: placeholders.baseUrl }],
    item: collection.item.map(withWidgetKey),
  }
}

export function postmanEnvironment() {
  return {
    name: 'Feedback Pilgrim',
    values: Object.entries(placeholders).map(([key, value]) => ({
      key,
      value,
      type: key === 'baseUrl' ? 'default' : 'secret',
      enabled: true,
    })),
    _postman_variable_scope: 'environment',
  }
}

function npx(cwd, ...args) {
  return execFileSync('npx', ['--yes', ...args], { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'inherit'] })
}

function writeJson(path, value) {
  writeFileSync(path, `${JSON.stringify(value, null, 2)}\n`)
}

function main() {
  const root = fileURLToPath(new URL('..', import.meta.url))
  const spec = join(root, 'docs/api/openapi.yaml')
  const scratch = mkdtempSync(join(tmpdir(), 'fp-api-docs-'))

  try {
    writeJson(join(root, 'public/docs/openapi.json'), JSON.parse(npx(scratch, JS_YAML, spec)))

    const converted = join(scratch, 'collection.json')
    npx(scratch, OPENAPI_TO_POSTMAN, '-s', spec, '-o', converted, '-O',
      'folderStrategy=Tags,parametersResolution=Example,alwaysInheritAuthentication=true,enableOptionalParameters=false')

    writeJson(join(root, 'docs/api/postman_collection.json'), postmanCollection(JSON.parse(readFileSync(converted, 'utf8'))))
    writeJson(join(root, 'docs/api/postman_environment.json'), postmanEnvironment())
  } finally {
    rmSync(scratch, { recursive: true, force: true })
  }
}

if (resolve(process.argv[1] ?? '') === fileURLToPath(import.meta.url)) {
  main()
}
