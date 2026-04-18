# ── Stage 1: serve static files with nginx ──────────────────────────────────
FROM nginx:alpine

# Remove the default nginx config
RUN rm /etc/nginx/conf.d/default.conf

# Copy the portfolio site
COPY output/ /usr/share/nginx/html/

# Copy the nginx config template
# We use a template so that Railway's injected $PORT is substituted at runtime
COPY nginx.conf /etc/nginx/templates/default.conf.template

# Railway injects PORT at runtime; 8080 is used for local `docker run` testing.
# envsubst will replace only $PORT in the template, leaving nginx variables
# like $uri and $host untouched.
ENV PORT=8080

EXPOSE 8080

# Substitute $PORT in the template → write the real config → start nginx
CMD ["/bin/sh", "-c", \
  "envsubst '$PORT' < /etc/nginx/templates/default.conf.template \
   > /etc/nginx/conf.d/default.conf && exec nginx -g 'daemon off;'"]
