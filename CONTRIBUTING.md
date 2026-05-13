# 貢獻指南

## 開新功能流程

1. `git checkout main && git pull origin main`
2. `git checkout -b feat/<你的字母>-<功能名>`
3. 寫 code、測試
4. `git add . && git commit -m "feat: 簡述"`
5. `git push origin feat/<你的字母>-<功能名>`
6. 到 GitHub 開 PR，target = main，指派 1 位 reviewer
7. review 通過 → merge → 刪除本機分支

## Commit Message 前綴

- `feat:` 新功能
- `fix:` 修 bug
- `docs:` 改文件
- `refactor:` 重構
- `style:` 純樣式
- `chore:` 雜項

## 絕對不要

- 直接 push main（已被分支保護擋下）
- commit `config.php` 或任何含密碼的檔案
- force push 共用分支
- 把 `node_modules/` 或大檔案塞進 repo
