/* app.jsx — 路由、登入守衛、掛載 */
window.favToggle = async (r, setList) => {
  try {
    const d = await api("POST", "/api/favorites/toggle", { restaurant_id: r.restaurant_id });
    setList(list => (list || []).map(x => x.restaurant_id === r.restaurant_id ? { ...x, is_favorited: d.is_favorited } : x));
    toast(d.is_favorited ? "已加入收藏" : "已取消收藏", d.is_favorited ? "ok" : "");
  } catch (e) {
    if (e.code === "unauthenticated") { toast("請先登入", "err"); navigate("#/login"); }
    else toast(e.message, "err");
  }
};

function FullLoading() { return <div style={{ height: "100vh", display: "flex", alignItems: "center", justifyContent: "center" }}><div className="spinner" /></div>; }

function App() {
  const route = useRoute();
  const [me, setMe] = useState(undefined); // undefined=載入中, null=未登入
  const refreshMe = async () => { try { const d = await api("GET", "/api/auth/me"); setMe(d.user); return d.user; } catch (e) { setMe(null); return null; } };
  useEffect(() => { refreshMe(); }, []);

  // 強制登入守衛
  useEffect(() => {
    if (me === undefined) return;
    if (!me && route.path !== "/login") navigate("#/login");
    if (me && route.path === "/login") navigate("#/");
  }, [me, route.path]);

  if (me === undefined) return <FullLoading />;
  if (!me && route.path !== "/login") return <FullLoading />;

  const onAuth = () => refreshMe();
  let page, showNav = me && route.path !== "/login";
  switch (route.path) {
    case "/login": page = <PageAuth onAuth={onAuth} />; break;
    case "/": page = <PageIndex me={me} />; break;
    case "/explore": page = <PageExplore me={me} />; break;
    case "/detail": page = <PageDetail me={me} />; break;
    case "/profile": page = <PageProfile me={me} onAuth={onAuth} />; break;
    case "/admin": page = <PageAdmin me={me} />; break;
    default: page = <PageIndex me={me} />;
  }
  return <React.Fragment>
    {showNav && <Navbar me={me} onAuth={onAuth} />}
    {page}
    <ToastHost /><ConfirmHost />
  </React.Fragment>;
}

ReactDOM.createRoot(document.getElementById("root")).render(<App />);
