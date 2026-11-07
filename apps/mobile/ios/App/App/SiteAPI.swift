import Foundation

/// The site the web view loads, as `capacitor.config.ts` configures it.
enum Site {
    static let url: URL = {
        struct Config: Decodable {
            struct Server: Decodable {
                let url: String
            }

            let server: Server
        }

        guard let file = Bundle.main.url(forResource: "capacitor.config", withExtension: "json"),
              let data = try? Data(contentsOf: file),
              let config = try? JSONDecoder().decode(Config.self, from: data),
              let url = URL(string: config.server.url) else {
            return URL(string: "https://dev-null.sh/")!
        }

        return url
    }()

    static func url(path: String) -> URL {
        URL(string: path, relativeTo: url)!.absoluteURL
    }

    /// Exactly the site's origin: a URL merely starting like it may be another site.
    static func contains(_ other: URL?) -> Bool {
        other?.scheme == url.scheme && other?.host == url.host && other?.port == url.port
    }
}

enum SiteAPIError: Error {
    /// The site doesn't know this key as a device: it was revoked, so signed out.
    case notApproved
    case banned
    case invalidCode
    case unreachable
    case failed(status: Int)
}

/// Requests to /api/v1, each signed with the device key as the site's
/// `SignedRequests` expects: "<timestamp>\n<nonce>\n<METHOD>\n<path>\n<body>".
struct SiteAPI {
    let key: DeviceKey

    /// Approves the key as a device, with the code the approval page sent back.
    func redeem(code: String, verifier: String) async throws {
        let body = try JSONSerialization.data(withJSONObject: ["code": code, "code_verifier": verifier])

        _ = try await post("/api/v1/devices", body: body)
    }

    /// A new web session for this device: its cookies, and whether the user has
    /// finished onboarding.
    ///
    /// The session has a cookie jar of its own: the shared one holds what Capacitor
    /// copies from the web view, and a jar gets cookies right where parsing joined
    /// headers wouldn't.
    func webSession() async throws -> (cookies: [HTTPCookie], isOnboarded: Bool) {
        let (data, jar) = try await post("/api/v1/me/web-session", body: Data())
        let answer = try? JSONSerialization.jsonObject(with: data) as? [String: Any]
        let cookies = jar.cookies(for: Site.url) ?? []

        guard !cookies.isEmpty else {
            throw SiteAPIError.failed(status: 200)
        }

        return (cookies, answer?["onboarded"] as? Bool ?? true)
    }

    private func post(_ path: String, body: Data) async throws -> (Data, HTTPCookieStorage) {
        let timestamp = String(Int(Date().timeIntervalSince1970))
        let nonce = Data.random(count: 16).hexString
        let message = Data("\(timestamp)\n\(nonce)\nPOST\n\(path)\n".utf8) + body

        var request = URLRequest(url: Site.url(path: path))
        request.httpMethod = "POST"
        request.httpBody = body
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue(timestamp, forHTTPHeaderField: "X-Devnull-Timestamp")
        request.setValue(nonce, forHTTPHeaderField: "X-Devnull-Nonce")
        request.setValue(try key.sign(message, namespace: "devnull-api"), forHTTPHeaderField: "X-SSH-Signature")

        let configuration = URLSessionConfiguration.ephemeral
        configuration.timeoutIntervalForRequest = 15
        let session = URLSession(configuration: configuration)
        defer { session.finishTasksAndInvalidate() }

        let data: Data
        let response: URLResponse

        do {
            (data, response) = try await session.data(for: request)
        } catch {
            throw SiteAPIError.unreachable
        }

        let status = (response as? HTTPURLResponse)?.statusCode ?? 0

        guard (200 ..< 300).contains(status) else {
            let answer = try? JSONSerialization.jsonObject(with: data) as? [String: Any]

            switch answer?["code"] as? String {
            // Only this one means the key is finished: a wrong clock is refused as an invalid signature
            case "device_not_approved":
                throw SiteAPIError.notApproved
            case "banned":
                throw SiteAPIError.banned
            case "invalid_grant":
                throw SiteAPIError.invalidCode
            default:
                throw SiteAPIError.failed(status: status)
            }
        }

        return (data, configuration.httpCookieStorage ?? HTTPCookieStorage())
    }
}
