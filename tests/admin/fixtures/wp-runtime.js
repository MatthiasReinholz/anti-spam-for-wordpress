/* Real React mounts the production bundle; only WordPress controls and transport
 * are substituted so request races and edits can be exercised deterministically. */
(() => {
  const { createElement: h, useId, useState } = React;
  const box = ({ children }) => h('div', null, children);
  const control = tag => props => {
    const id = useId();
    const { label, value, onChange, type, disabled, checked, readOnly, rows } = props;
    return h('label', { htmlFor: id }, label, h(tag, {
      id, value, type, disabled, checked, readOnly, rows,
      onChange: event => onChange?.(type === 'checkbox' ? event.target.checked : event.target.value),
    }, tag === 'select' ? props.options.map(option => h('option', { key: option.value, value: option.value }, option.label)) : undefined));
  };
  window.adminCalls = [];
  window.adminFailures = [];
  window.wpPluginBaseAdminUi = { 'anti-spam-for-wordpress': {
    rootId: 'app', pluginName: 'Anti Spam for WordPress', restNamespace: 'anti-spam-for-wordpress/v1',
    operations: {
      'settings.read': { route: '/admin/settings' }, 'settings.update': { route: '/admin/settings' },
      'events.list': { route: '/admin/events' }, 'analytics.read': { route: '/admin/analytics' },
    },
  } };
  window.wp = {
    element: { ...React, createRoot: target => {
      const root = ReactDOM.createRoot(target);
      window.adminRoot = root;
      return root;
    } },
    i18n: { __: text => text },
    components: {
      Card: box, CardHeader: box, CardBody: box, Flex: box, FlexBlock: box, Panel: box, PanelBody: box,
      Button: ({ children, onClick, disabled, type = 'button' }) => h('button', { onClick, disabled, type }, children),
      Spinner: () => h('div', { role: 'status' }, 'Loading'),
      Notice: ({ children }) => h('div', { role: 'alert' }, children),
      TextControl: control('input'), TextareaControl: control('textarea'), SelectControl: control('select'),
      CheckboxControl: props => control('input')({ ...props, type: 'checkbox' }),
      TabPanel: ({ tabs, initialTabName, onSelect, children }) => {
        const [ selected, select ] = useState(initialTabName);
        return h('div', null, h('div', { role: 'tablist' }, tabs.map(tab => h('button', {
          role: 'tab', key: tab.name, 'aria-selected': selected === tab.name,
          onClick: () => { select(tab.name); onSelect(tab.name); },
        }, tab.title))), children(tabs.find(tab => tab.name === selected)));
      },
    },
    apiFetch: async options => {
      window.adminCalls.push({ path: options.path, method: options.method || 'GET', data: options.data });
      const response = await fetch(`/api${options.path}`, {
        method: options.method || 'GET', headers: { 'Content-Type': 'application/json' },
        body: options.data ? JSON.stringify(options.data) : undefined,
        signal: window.ignoreAdminAbort ? undefined : options.signal,
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || `Request failed (${response.status})`);
      return data;
    },
  };
  window.addEventListener('unhandledrejection', event => window.adminFailures.push(String(event.reason)));
})();
