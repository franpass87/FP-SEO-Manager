# FP-SEO-Manager

## Release process

1. Ensure prerequisites from [README-BUILD](README-BUILD.md) are available.
2. Run the build script to bump the version (patch by default) and generate the ZIP:
   ```bash
   bash build.sh --bump=patch
   ```
   or set an explicit version:
   ```bash
   bash build.sh --set-version=1.2.3
   ```
3. Commit the bumped version and generated artifacts.
4. (Optional) Push a tag like `v1.2.3` to trigger the GitHub Action that uploads the packaged ZIP artifact.
