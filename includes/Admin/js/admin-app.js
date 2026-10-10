/* ATA Admin React SPA — MVP */
(function (wp) {
  var e = wp.element;
  var c = wp.components;
  var i18n = wp.i18n;
  var __ = i18n.__;
  var useState = e.useState;
  var useEffect = e.useEffect;

  function api(path, opts) {
    opts = opts || {};
    return fetch(ATA_REST_URL.url + path.replace(/^\//, ''), Object.assign({
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    }, opts)).then(function (r) { return r.json().catch(function () { return {}; }); });
  }

  var sections = [
    ['dashboard', 'داشبورد', 'dashboard'],
    ['generated', 'متن‌های تولید شده', 'media-document'],
    ['content', 'ایجاد محتوا', 'edit'],
    ['scheduler', 'زمان‌بندی', 'calendar'],
    ['queue', 'صف انتشار', 'sort'],
    ['ai-providers', 'پروVIDERها', 'admin-network'],
    ['ai-generate', 'AI تولید', 'admin-post'],
    ['channels', 'کانال‌ها', 'groups'],
    ['posts', 'پست‌ها', 'admin-post'],
    ['logs', 'لاگ‌ها', 'marker'],
    ['telegram', 'تلگرام', 'share'],
    ['license', 'لایسنس', 'awards'],
    ['settings', 'تنظیمات', 'admin-settings'],
    ['help', 'راهنما', 'editor-help'],
  ];

  function Dashboard() {
    var data = useState(null);
    var set = data[1];
    var loading = useState(true);
    var setL = loading[1];
    useEffect(function () {
      api('/dashboard').then(function (d) { set(d); setL(false); });
    }, []);
    if (loading[0]) return e.createElement(c.Spinner);
    if (!data[0]) return e.createElement(c.Notice, { status: 'error' }, 'خطا');
    return e.createElement('div', null,
      e.createElement(c.Card, null,
        e.createElement(c.CardHeader, null, e.createElement('h3', null, 'داشبورد')),
        e.createElement(c/CardBody, null,
          e.createElement('p', null, 'کانال‌ها: ' + (data[0].channels && data[0].channels.length || 0)),
          e.createElement('p', null, 'صف: ' + (data[0].queue && data[0].queue.length || 0))
        )
      )
    );
  }

  function TelegramConnect() {
    var token = useState('');
    var setToken = token[1];
    var saving = useState(false);
    var setSaving = saving[1];
    var msg = useState('');
    var setMsg = msg[1];
    function save() {
      setSaving(true);
      api('/telegram/connect', { method: 'POST', body: JSON.stringify({ token: token[0] }) })
        .then(function (d) {
          setMsg(d.success ? 'اتصال موفق' : (d.message || 'خطا'));
          setSaving(false);
        });
    }
    return e.createElement('div', null,
      e.createElement(c.TextControl, { label: 'Bot Token', value: token[0], onChange: setToken, disabled: saving[0] }),
      e.createElement(c.Button, { isPrimary: true, onClick: save, disabled: saving[0] || !token[0] }, 'ذخیره'),
      msg[0] && e.createElement(c.Notice, { status: msg[0].includes('موفق') ? 'success' : 'error' }, msg[0])
    );
  }

  var map = { dashboard: Dashboard, telegram: TelegramConnect };
  function Section(props) {
    var Comp = map[props.id];
    return Comp ? e.createElement(Comp) : e.createElement('p', null, 'بخش ' + props.id + ' در حال ساخت…');
  }

  function App() {
    var active = useState('dashboard');
    var setActive = active[1];
    return e.createElement('div', { className: 'ata-admin-wrap' },
      e.createElement('div', { className: 'ata-sidebar' },
        sections.map(function (s) {
          return e.createElement(c.Button, {
            key: s[0], variant: active[0] === s[0] ? 'primary' : 'secondary',
            onClick: function () { setActive(s[0]); },
          }, s[1]);
        })
      ),
      e.createElement('div', { className: 'ata-main' },
        e.createElement(Section, { id: active[0] })
      )
    );
  }

  wp.element.render(e.createElement(App), document.getElementById('ata-admin-root'));
})(window.wp);