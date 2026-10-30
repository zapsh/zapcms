/**
 * ZAP CMS 代码编辑器（CodeMirror 6）
 * ---------------------------------------------------------------------------
 * 该文件是独立打包入口，产物：assets/admin/js/editor.js
 * 由「开发工具 → 文件编辑」在首次打开文件时通过动态 <script type="module">
 * 按需加载；语言包再按文件后缀单独 import()，未打开的文件类型不会下载。
 *
 * 对外只暴露 window.ZapEditor.create(parent, { filename, content, onChange })，
 * 返回对象刻意保留 Ace 风格的方法名（getValue/setValue/resetUndo/resize/destroy），
 * 便于业务代码平滑迁移。
 */
import { basicSetup } from 'codemirror';
import { Compartment, EditorSelection, EditorState } from '@codemirror/state';
import { EditorView } from '@codemirror/view';
import { defaultHighlightStyle, indentUnit, syntaxHighlighting } from '@codemirror/language';
import { oneDarkHighlightStyle } from '@codemirror/theme-one-dark';

// 明暗主题共用的框架样式：底色/字号/行号栏全部走 Bootstrap 变量，跟随后台主题
const BASE_THEME = EditorView.theme({
  '&': { height: '100%', backgroundColor: 'var(--bs-body-bg)', color: 'var(--bs-body-color)' },
  '.cm-scroller': { fontFamily: "Consolas, Menlo, Monaco, 'Courier New', monospace", fontSize: '14px' },
  '.cm-content': { paddingBottom: '3rem' },
  '.cm-gutters': {
    backgroundColor: 'var(--bs-tertiary-bg)',
    color: 'var(--bs-secondary-color)',
    borderRight: '1px solid var(--bs-border-color)',
  },
  '.cm-activeLine': { backgroundColor: 'var(--bs-secondary-bg)' },
  '.cm-activeLineGutter': { backgroundColor: 'var(--bs-secondary-bg)' },
  '&.cm-focused .cm-selectionBackground, .cm-selectionBackground, ::selection': {
    backgroundColor: 'var(--bs-primary-bg-subtle) !important',
  },
});

function isDarkTheme() {
  return document.documentElement.getAttribute('data-bs-theme') === 'dark';
}

// 语法高亮随主题切换（后台通过 zap:theme 事件通知）
function highlightExtension() {
  return isDarkTheme()
    ? syntaxHighlighting(oneDarkHighlightStyle)
    : syntaxHighlighting(defaultHighlightStyle, { fallback: true });
}

// 后缀 → 语言包（按需加载）
const LANGUAGES = [
  { match: /\.(php\d?|phtml|inc)$/i, load: () => import('@codemirror/lang-php').then((m) => m.php()) },
  { match: /\.(html?|xhtml|twig|tpl|hbs)$/i, load: () => import('@codemirror/lang-html').then((m) => m.html()) },
  { match: /\.(css|scss|less)$/i, load: () => import('@codemirror/lang-css').then((m) => m.css()) },
  { match: /\.(m?js|cjs|jsx)$/i, load: () => import('@codemirror/lang-javascript').then((m) => m.javascript({ jsx: true })) },
  { match: /\.(ts|tsx|mts|cts)$/i, load: () => import('@codemirror/lang-javascript').then((m) => m.javascript({ jsx: true, typescript: true })) },
  { match: /\.json5?$/i, load: () => import('@codemirror/lang-json').then((m) => m.json()) },
  { match: /\.(md|markdown)$/i, load: () => import('@codemirror/lang-markdown').then((m) => m.markdown()) },
  { match: /\.(xml|svg|xsl|xslt|plist)$/i, load: () => import('@codemirror/lang-xml').then((m) => m.xml()) },
  { match: /\.sql$/i, load: () => import('@codemirror/lang-sql').then((m) => m.sql()) },
  { match: /\.ya?ml$/i, load: () => import('@codemirror/lang-yaml').then((m) => m.yaml()) },
];

async function detectLanguage(filename) {
  const hit = LANGUAGES.find((item) => item.match.test(filename || ''));
  if (!hit) return null;

  try {
    return await hit.load();
  } catch (err) {
    // 语言包下载失败不影响纯文本编辑
    console.warn('[ZapEditor] language support failed:', err);
    return null;
  }
}

/**
 * 创建编辑器实例
 */
function create(parent, { filename = '', content = '', onChange } = {}) {
  const languageSlot = new Compartment();
  const highlightSlot = new Compartment();
  let language = null;
  let destroyed = false;

  const buildExtensions = () => [
    basicSetup,
    BASE_THEME,
    EditorState.tabSize.of(4),
    indentUnit.of('    '),
    languageSlot.of(language ?? []),
    highlightSlot.of(highlightExtension()),
    EditorView.updateListener.of((update) => {
      if (update.docChanged && typeof onChange === 'function') onChange();
    }),
  ];

  const view = new EditorView({
    state: EditorState.create({ doc: content, extensions: buildExtensions() }),
    parent,
  });

  // 语言包异步挂载，不阻塞首次渲染
  detectLanguage(filename).then((support) => {
    if (destroyed || !support) return;
    language = support;
    view.dispatch({ effects: languageSlot.reconfigure(support) });
  });

  // 整篇替换（同时清空撤销历史），保留光标位置
  function replaceDoc(doc) {
    const head = Math.min(view.state.selection.main.head, doc.length);
    view.setState(EditorState.create({
      doc,
      selection: EditorSelection.cursor(head),
      extensions: buildExtensions(),
    }));
    view.requestMeasure();
  }

  // 后台切换明暗主题时同步语法高亮
  function onThemeChange() {
    if (destroyed) return;
    view.dispatch({ effects: highlightSlot.reconfigure(highlightExtension()) });
  }
  window.addEventListener('zap:theme', onThemeChange);

  return {
    view,
    getValue: () => view.state.doc.toString(),
    setValue: replaceDoc,
    resetUndo: () => replaceDoc(view.state.doc.toString()),
    // 容器从 display:none 恢复显示后重新测量，否则高度/滚动区为 0
    resize: () => view.requestMeasure(),
    focus: () => view.focus(),
    destroy: () => {
      destroyed = true;
      window.removeEventListener('zap:theme', onThemeChange);
      view.destroy();
    },
  };
}

window.ZapEditor = { create };
