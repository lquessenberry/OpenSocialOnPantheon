# You can find the new timestamped tags here: https://hub.docker.com/r/gitpod/workspace-full/tags
FROM gitpod/workspace-full

# Change your version here
RUN sudo update-alternatives --set php $(which php8.1)
