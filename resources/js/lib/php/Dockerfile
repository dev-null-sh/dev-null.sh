# Builds the PHP that runs PHP code blocks, as a WebAssembly module for the browser.
# Adapted from https://github.com/derickr/php-wasm-builder (MIT).
#
#   docker build --output type=local,dest=storage/php resources/js/lib/php
#   cp storage/php/php.mjs storage/php/php.wasm resources/js/lib/php/ && cp storage/php/licenses/* public/licenses/php/

FROM debian:bookworm-slim@sha256:7ff6ba31527b9e336162fa061746c366c4b2032f4d2cff2078bea43f78efe3f8 AS build

ARG PHP_VERSION=8.5.12
ARG PHP_COMMIT=136e049ac10b032c83018e65b63b6df858943750
ARG EMSDK_VERSION=6.0.13
ARG EMSDK_COMMIT=35659964bc2762fffec0d257997fb61475f765b7

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		autoconf automake bison build-essential ca-certificates file flex git libtool make pkg-config python3 re2c xz-utils \
	&& rm -rf /var/lib/apt/lists/*

WORKDIR /src

RUN git clone --depth 1 --branch $EMSDK_VERSION https://github.com/emscripten-core/emsdk.git \
	&& cd emsdk \
	&& test "$(git rev-parse HEAD)" = "$EMSDK_COMMIT" \
	&& ./emsdk install $EMSDK_VERSION \
	&& ./emsdk activate $EMSDK_VERSION

ENV PATH=/src/emsdk:/src/emsdk/upstream/emscripten:$PATH

RUN git clone --depth 1 --branch php-$PHP_VERSION https://github.com/php/php-src.git \
	&& cd php-src \
	&& test "$(git rev-parse HEAD)" = "$PHP_COMMIT" \
	&& ./buildconf --force

RUN cd php-src \
	&& emconfigure ./configure \
		--host=wasm32-unknown-emscripten \
		--enable-embed=static \
		--disable-all \
		--disable-cgi --disable-cli --disable-phpdbg \
		--disable-fiber-asm --disable-opcache-jit \
		--without-pcre-jit \
		--enable-ctype --enable-filter --enable-tokenizer \
	&& emmake make -j"$(nproc)"

COPY phpw.c /src/phpw.c

RUN mkdir -p /out \
	&& emcc -O2 -I php-src -I php-src/Zend -I php-src/main -I php-src/TSRM -c phpw.c -o phpw.o \
	&& emcc -o /out/php.mjs -O2 \
		-s EXPORTED_FUNCTIONS='["_phpw"]' \
		-s EXPORTED_RUNTIME_METHODS='["ccall","FS"]' \
		-s ENVIRONMENT=web,worker \
		-s MODULARIZE=1 -s EXPORT_ES6=1 -s EXPORT_NAME=createPhp -s INVOKE_RUN=0 \
		-s INITIAL_MEMORY=128mb -s MAXIMUM_MEMORY=128mb -s ALLOW_MEMORY_GROWTH=0 -s STACK_SIZE=2mb \
		-s DYNAMIC_EXECUTION=0 -s ASSERTIONS=0 -s ERROR_ON_UNDEFINED_SYMBOLS=0 \
		phpw.o php-src/.libs/libphp.a \
	&& mkdir -p /out/licenses \
	&& cp php-src/LICENSE /out/licenses/PHP.txt \
	&& cp php-src/Zend/LICENSE /out/licenses/Zend.txt \
	&& cp php-src/README.REDIST.BINS /out/licenses/php-bundled-libraries.txt \
	&& cp php-src/ext/lexbor/LICENSE /out/licenses/lexbor-LICENSE.txt \
	&& cp php-src/ext/lexbor/NOTICE /out/licenses/lexbor-NOTICE.txt \
	&& for file in php-src/ext/uri/uriparser/COPYING*; do cp "$file" "/out/licenses/uriparser-$(basename "$file").txt"; done \
	&& cp emsdk/upstream/emscripten/LICENSE /out/licenses/emscripten-LICENSE.txt \
	&& cp emsdk/upstream/emscripten/system/lib/libc/musl/COPYRIGHT /out/licenses/musl-COPYRIGHT.txt

FROM scratch
COPY --from=build /out/ /
